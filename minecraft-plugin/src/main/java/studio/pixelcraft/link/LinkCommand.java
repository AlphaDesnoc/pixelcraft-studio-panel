package studio.pixelcraft.link;

import org.bukkit.ChatColor;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;

public final class LinkCommand implements CommandExecutor {

    private static final String PREFIX = ChatColor.AQUA + "[PixelCraftLink] " + ChatColor.RESET;

    private final PixelCraftLink plugin;

    public LinkCommand(PixelCraftLink plugin) {
        this.plugin = plugin;
    }

    @Override
    public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        if (args.length == 1 && args[0].equalsIgnoreCase("sync")) {
            sender.sendMessage(PREFIX + "Synchronisation des joueurs en ligne…");
            plugin.syncOnlinePlayers();
            return true;
        }

        if (args.length == 1 && args[0].equalsIgnoreCase("status")) {
            boolean configured = plugin.client() != null && plugin.client().isConfigured();
            sender.sendMessage(PREFIX + (configured
                ? ChatColor.GREEN + "Serveur relié au panel."
                : ChatColor.YELLOW + "Aucune liaison configurée."));
            return true;
        }

        // /pixellink <identifiant>  (URL lue dans config.yml)
        if (args.length == 1) {
            String url = plugin.panelUrl();
            if (url == null || url.isEmpty()) {
                sender.sendMessage(PREFIX + ChatColor.RED + "URL du panel non configurée.");
                sender.sendMessage(PREFIX + ChatColor.GRAY + "Renseignez panel-url dans config.yml, ou utilisez /pixellink <url> <identifiant>.");
                return true;
            }
            sender.sendMessage(PREFIX + "Liaison en cours…");
            plugin.claim(sender, url, args[0]);
            return true;
        }

        // /pixellink <url> <identifiant>
        if (args.length == 2) {
            sender.sendMessage(PREFIX + "Liaison en cours…");
            plugin.claim(sender, args[0], args[1]);
            return true;
        }

        sender.sendMessage(PREFIX + ChatColor.YELLOW + "Usage : /pixellink <identifiant>");
        sender.sendMessage(PREFIX + ChatColor.GRAY + "1re fois : /pixellink <url-panel> <identifiant>");
        sender.sendMessage(PREFIX + ChatColor.GRAY + "Autres : /pixellink status · /pixellink sync");
        return true;
    }
}
