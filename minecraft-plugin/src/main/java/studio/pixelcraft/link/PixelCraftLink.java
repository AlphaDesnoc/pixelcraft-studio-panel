package studio.pixelcraft.link;

import java.net.InetSocketAddress;
import java.util.StringJoiner;
import org.bukkit.ChatColor;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.scheduler.BukkitTask;

public final class PixelCraftLink extends JavaPlugin {

    private volatile PanelClient client;
    private BukkitTask syncTask;

    @Override
    public void onEnable() {
        saveDefaultConfig();
        reloadClient();

        getServer().getPluginManager().registerEvents(new PlayerConnectionListener(this), this);
        getCommand("pixellink").setExecutor(new LinkCommand(this));

        scheduleSync();

        if (client != null && client.isConfigured()) {
            getLogger().info("Liaison configurée, synchronisation initiale…");
            syncOnlinePlayers();
        } else {
            getLogger().warning("Serveur non relié. Utilisez /pixellink <url-panel> <token> pour le relier.");
        }
    }

    @Override
    public void onDisable() {
        if (syncTask != null) {
            syncTask.cancel();
        }
    }

    public PanelClient client() {
        return client;
    }

    /** Recrée le client HTTP depuis la configuration courante. */
    public void reloadClient() {
        String url = getConfig().getString("panel-url", "");
        String token = getConfig().getString("token", "");
        this.client = new PanelClient(url, token);
    }

    public String panelUrl() {
        return getConfig().getString("panel-url", "");
    }

    /**
     * Relie le serveur via l'identifiant court : échange l'identifiant contre le
     * token permanent puis le stocke. Tout se fait de façon asynchrone.
     */
    public void claim(CommandSender sender, String url, String code) {
        getConfig().set("panel-url", url);
        saveConfig();
        reloadClient();

        final String prefix = ChatColor.AQUA + "[PixelCraftLink] " + ChatColor.RESET;
        final PanelClient pending = client();

        getServer().getScheduler().runTaskAsynchronously(this, () -> {
            String body = "{\"code\":\"" + PanelClient.escape(code) + "\","
                + "\"server_name\":\"" + PanelClient.escape(getServer().getName()) + "\"}";
            PanelClient.Result result = pending.post("/claim", body);

            getServer().getScheduler().runTask(this, () -> {
                if (result.ok()) {
                    String token = PanelClient.extractString(result.body(), "token");
                    if (token == null || token.isEmpty()) {
                        sender.sendMessage(prefix + ChatColor.RED + "Réponse du panel invalide (token manquant).");
                        return;
                    }
                    getConfig().set("token", token);
                    saveConfig();
                    reloadClient();
                    scheduleSync();
                    sender.sendMessage(prefix + ChatColor.GREEN + "Serveur relié avec succès !");
                    syncOnlinePlayers();
                } else if (result.status() == 404) {
                    sender.sendMessage(prefix + ChatColor.RED + "Identifiant invalide. Vérifiez-le dans l'onglet Joueurs du projet.");
                } else if (result.status() == -1) {
                    sender.sendMessage(prefix + ChatColor.RED + "Panel injoignable. Vérifiez l'URL : " + url);
                } else {
                    sender.sendMessage(prefix + ChatColor.RED + "Échec de la liaison (HTTP " + result.status() + ").");
                }
            });
        });
    }

    private void scheduleSync() {
        if (syncTask != null) {
            syncTask.cancel();
            syncTask = null;
        }
        long intervalTicks = Math.max(30, getConfig().getLong("sync-interval", 300)) * 20L;
        syncTask = getServer().getScheduler().runTaskTimerAsynchronously(
            this,
            this::syncOnlinePlayers,
            intervalTicks,
            intervalTicks
        );
    }

    /** Envoie un appel POST de façon asynchrone. */
    public void postAsync(String path, String json) {
        if (client == null || !client.isConfigured()) {
            return;
        }
        getServer().getScheduler().runTaskAsynchronously(this, () -> {
            PanelClient.Result result = client.post(path, json);
            if (!result.ok()) {
                getLogger().warning("Échec de l'appel " + path + " (HTTP " + result.status() + ") : " + result.body());
            }
        });
    }

    /** Construit le JSON d'un joueur et l'envoie (connexion). */
    public void sendJoin(Player player) {
        postAsync("/players/join", playerJson(player));
    }

    public void sendQuit(Player player) {
        String json = "{\"uuid\":\"" + PanelClient.escape(player.getUniqueId().toString()) + "\"}";
        postAsync("/players/quit", json);
    }

    /** Synchronise l'ensemble des joueurs actuellement connectés. */
    public void syncOnlinePlayers() {
        if (client == null || !client.isConfigured()) {
            return;
        }
        StringJoiner players = new StringJoiner(",", "[", "]");
        for (Player player : getServer().getOnlinePlayers()) {
            players.add(playerJson(player));
        }
        postAsync("/players/sync", "{\"players\":" + players + "}");
    }

    public String playerJson(Player player) {
        String ip = "";
        InetSocketAddress address = player.getAddress();
        if (address != null && address.getAddress() != null) {
            ip = address.getAddress().getHostAddress();
        }
        return "{"
            + "\"uuid\":\"" + PanelClient.escape(player.getUniqueId().toString()) + "\","
            + "\"name\":\"" + PanelClient.escape(player.getName()) + "\","
            + "\"ip\":\"" + PanelClient.escape(ip) + "\""
            + "}";
    }
}
