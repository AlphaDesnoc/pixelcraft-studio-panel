package studio.pixelcraft.link;

import com.google.inject.Inject;
import com.velocitypowered.api.command.CommandManager;
import com.velocitypowered.api.command.CommandMeta;
import com.velocitypowered.api.command.CommandSource;
import com.velocitypowered.api.event.Subscribe;
import com.velocitypowered.api.event.connection.DisconnectEvent;
import com.velocitypowered.api.event.connection.PostLoginEvent;
import com.velocitypowered.api.event.player.ServerConnectedEvent;
import com.velocitypowered.api.event.proxy.ProxyInitializeEvent;
import com.velocitypowered.api.plugin.Plugin;
import com.velocitypowered.api.plugin.annotation.DataDirectory;
import com.velocitypowered.api.proxy.Player;
import com.velocitypowered.api.proxy.ProxyServer;
import com.velocitypowered.api.scheduler.ScheduledTask;
import java.io.IOException;
import java.net.InetSocketAddress;
import java.nio.file.Path;
import java.util.StringJoiner;
import java.util.concurrent.TimeUnit;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.format.NamedTextColor;
import org.slf4j.Logger;

@Plugin(
    id = "pixelcraftlink",
    name = "PixelCraftLink",
    version = "2.0.0",
    description = "Synchronise les joueurs (UUID + IP) et leur serveur courant vers le panel PixelCraft Studio.",
    authors = {"PixelCraft Studio"}
)
public final class PixelCraftLink {

    static final Component PREFIX = Component.text("[PixelCraftLink] ", NamedTextColor.AQUA);

    private final ProxyServer proxy;
    private final Logger logger;
    private final Path dataDirectory;

    private PluginConfig config;
    private volatile PanelClient client;
    private ScheduledTask syncTask;

    @Inject
    public PixelCraftLink(ProxyServer proxy, Logger logger, @DataDirectory Path dataDirectory) {
        this.proxy = proxy;
        this.logger = logger;
        this.dataDirectory = dataDirectory;
    }

    @Subscribe
    public void onProxyInitialize(ProxyInitializeEvent event) {
        try {
            this.config = new PluginConfig(dataDirectory);
        } catch (IOException e) {
            logger.error("Impossible de charger la configuration", e);
            return;
        }
        reloadClient();

        CommandManager commands = proxy.getCommandManager();
        CommandMeta meta = commands.metaBuilder("pixellink").plugin(this).build();
        commands.register(meta, new LinkCommand(this));

        scheduleSync();

        if (client != null && client.isConfigured()) {
            logger.info("Liaison configurée, synchronisation initiale…");
            syncOnlinePlayers();
        } else {
            logger.warn("Proxy non relié. Utilisez « pixellink <url-panel> <identifiant> » pour le relier.");
        }
    }

    public ProxyServer proxy() {
        return proxy;
    }

    public PanelClient client() {
        return client;
    }

    /** Recrée le client HTTP depuis la configuration courante. */
    public void reloadClient() {
        String url = config.getString("panel-url", "");
        String token = config.getString("token", "");
        this.client = new PanelClient(url, token);
    }

    /**
     * Recharge config.toml depuis le disque, recrée le client HTTP et
     * reprogramme la synchronisation. Renvoie false en cas d'erreur de lecture.
     */
    public boolean reloadConfig() {
        try {
            config.load();
        } catch (IOException e) {
            logger.error("Impossible de recharger config.toml", e);
            return false;
        }
        reloadClient();
        scheduleSync();
        return true;
    }

    public String panelUrl() {
        return config.getString("panel-url", "");
    }

    private String serverName() {
        return config.getString("server-name", "Proxy Velocity");
    }

    /**
     * Relie le proxy via l'identifiant court : échange l'identifiant contre le
     * token permanent puis le stocke. Tout se fait de façon asynchrone.
     */
    public void claim(CommandSource sender, String url, String code) {
        config.set("panel-url", url);
        saveConfig();
        reloadClient();

        final PanelClient pending = client();

        proxy.getScheduler().buildTask(this, () -> {
            String body = "{\"code\":\"" + PanelClient.escape(code) + "\","
                + "\"server_name\":\"" + PanelClient.escape(serverName()) + "\"}";
            PanelClient.Result result = pending.post("/claim", body);

            if (result.ok()) {
                String token = PanelClient.extractString(result.body(), "token");
                if (token == null || token.isEmpty()) {
                    sender.sendMessage(PREFIX.append(Component.text(
                        "Réponse du panel invalide (token manquant).", NamedTextColor.RED)));
                    return;
                }
                config.set("token", token);
                saveConfig();
                reloadClient();
                scheduleSync();
                sender.sendMessage(PREFIX.append(Component.text(
                    "Proxy relié avec succès !", NamedTextColor.GREEN)));
                syncOnlinePlayers();
            } else if (result.status() == 404) {
                sender.sendMessage(PREFIX.append(Component.text(
                    "Identifiant invalide. Vérifiez-le dans l'onglet Joueurs du projet.", NamedTextColor.RED)));
            } else if (result.status() == -1) {
                sender.sendMessage(PREFIX.append(Component.text(
                    "Panel injoignable. Vérifiez l'URL : " + url, NamedTextColor.RED)));
            } else {
                sender.sendMessage(PREFIX.append(Component.text(
                    "Échec de la liaison (HTTP " + result.status() + ").", NamedTextColor.RED)));
            }
        }).schedule();
    }

    private void scheduleSync() {
        if (syncTask != null) {
            syncTask.cancel();
            syncTask = null;
        }
        long interval = Math.max(15, config.getLong("sync-interval", 60));
        syncTask = proxy.getScheduler().buildTask(this, this::syncOnlinePlayers)
            .delay(interval, TimeUnit.SECONDS)
            .repeat(interval, TimeUnit.SECONDS)
            .schedule();
    }

    public void saveConfig() {
        try {
            config.save();
        } catch (IOException e) {
            logger.error("Impossible d'enregistrer la configuration", e);
        }
    }

    /** Envoie un POST de façon asynchrone (jamais sur le thread Netty). */
    public void postAsync(String path, String json) {
        if (client == null || !client.isConfigured()) {
            return;
        }
        proxy.getScheduler().buildTask(this, () -> {
            PanelClient.Result result = client.post(path, json);
            if (!result.ok()) {
                logger.warn("Échec de l'appel {} (HTTP {}) : {}", path, result.status(), result.body());
            }
        }).schedule();
    }

    // ----- Événements proxy -------------------------------------------------

    /** Connexion d'un joueur au proxy : capture UUID, pseudo et IP réelle. */
    @Subscribe
    public void onPostLogin(PostLoginEvent event) {
        postAsync("/players/join", playerJson(event.getPlayer(), null));
    }

    /** Arrivée sur un serveur backend (connexion initiale ou changement). */
    @Subscribe
    public void onServerConnected(ServerConnectedEvent event) {
        String serverName = event.getServer().getServerInfo().getName();
        postAsync("/players/server", playerJson(event.getPlayer(), serverName));
    }

    /** Déconnexion du proxy. */
    @Subscribe
    public void onDisconnect(DisconnectEvent event) {
        String json = "{\"uuid\":\"" + PanelClient.escape(event.getPlayer().getUniqueId().toString()) + "\"}";
        postAsync("/players/quit", json);
    }

    /** Synchronise l'ensemble des joueurs actuellement connectés au réseau. */
    public void syncOnlinePlayers() {
        if (client == null || !client.isConfigured()) {
            return;
        }
        StringJoiner players = new StringJoiner(",", "[", "]");
        for (Player player : proxy.getAllPlayers()) {
            String server = player.getCurrentServer()
                .map(conn -> conn.getServerInfo().getName())
                .orElse(null);
            players.add(playerJson(player, server));
        }
        long interval = Math.max(15, config.getLong("sync-interval", 60));
        postAsync("/players/sync", "{\"interval\":" + interval + ",\"players\":" + players + "}");
    }

    /**
     * Construit le JSON d'un joueur. {@code server} peut être nul (joueur pas
     * encore arrivé sur un backend) auquel cas le champ est omis.
     */
    public String playerJson(Player player, String server) {
        String ip = "";
        InetSocketAddress address = player.getRemoteAddress();
        if (address != null && address.getAddress() != null) {
            ip = address.getAddress().getHostAddress();
        }
        StringBuilder sb = new StringBuilder("{");
        sb.append("\"uuid\":\"").append(PanelClient.escape(player.getUniqueId().toString())).append("\",");
        sb.append("\"name\":\"").append(PanelClient.escape(player.getUsername())).append("\",");
        sb.append("\"ip\":\"").append(PanelClient.escape(ip)).append("\"");
        if (server != null) {
            sb.append(",\"server\":\"").append(PanelClient.escape(server)).append("\"");
        }
        sb.append("}");
        return sb.toString();
    }
}
