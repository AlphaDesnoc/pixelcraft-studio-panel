package studio.pixelcraft.link;

import com.velocitypowered.api.command.SimpleCommand;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.format.NamedTextColor;

public final class LinkCommand implements SimpleCommand {

    private final PixelCraftLink plugin;

    public LinkCommand(PixelCraftLink plugin) {
        this.plugin = plugin;
    }

    @Override
    public void execute(Invocation invocation) {
        var source = invocation.source();
        String[] args = invocation.arguments();

        if (args.length == 1 && args[0].equalsIgnoreCase("sync")) {
            source.sendMessage(PixelCraftLink.PREFIX.append(
                Component.text("Synchronisation des joueurs en ligne…", NamedTextColor.WHITE)));
            plugin.syncOnlinePlayers();
            return;
        }

        if (args.length == 1 && args[0].equalsIgnoreCase("status")) {
            boolean configured = plugin.client() != null && plugin.client().isConfigured();
            source.sendMessage(PixelCraftLink.PREFIX.append(configured
                ? Component.text("Proxy relié au panel.", NamedTextColor.GREEN)
                : Component.text("Aucune liaison configurée.", NamedTextColor.YELLOW)));
            return;
        }

        // pixellink <identifiant>  (URL lue dans config.toml)
        if (args.length == 1) {
            String url = plugin.panelUrl();
            if (url == null || url.isEmpty()) {
                source.sendMessage(PixelCraftLink.PREFIX.append(
                    Component.text("URL du panel non configurée.", NamedTextColor.RED)));
                source.sendMessage(PixelCraftLink.PREFIX.append(Component.text(
                    "Renseignez panel-url dans config.toml, ou utilisez « pixellink <url> <identifiant> ».",
                    NamedTextColor.GRAY)));
                return;
            }
            source.sendMessage(PixelCraftLink.PREFIX.append(
                Component.text("Liaison en cours…", NamedTextColor.WHITE)));
            plugin.claim(source, url, args[0]);
            return;
        }

        // pixellink <url> <identifiant>
        if (args.length == 2) {
            source.sendMessage(PixelCraftLink.PREFIX.append(
                Component.text("Liaison en cours…", NamedTextColor.WHITE)));
            plugin.claim(source, args[0], args[1]);
            return;
        }

        source.sendMessage(PixelCraftLink.PREFIX.append(
            Component.text("Usage : pixellink <identifiant>", NamedTextColor.YELLOW)));
        source.sendMessage(PixelCraftLink.PREFIX.append(Component.text(
            "1re fois : pixellink <url-panel> <identifiant>", NamedTextColor.GRAY)));
        source.sendMessage(PixelCraftLink.PREFIX.append(Component.text(
            "Autres : pixellink status · pixellink sync", NamedTextColor.GRAY)));
    }

    @Override
    public boolean hasPermission(Invocation invocation) {
        // La console possède toujours la permission ; en jeu, réservé aux admins.
        return invocation.source().hasPermission("pixelcraftlink.admin");
    }
}
