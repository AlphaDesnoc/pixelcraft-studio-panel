package studio.pixelcraft.link;

import java.io.IOException;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Petit gestionnaire de configuration TOML « à plat » (clé = valeur), sans
 * dépendance externe. Suffisant pour les quelques options de PixelCraftLink.
 * Préserve les commentaires/ordre du fichier modèle lors de la première copie,
 * puis réécrit un fichier simple lors des sauvegardes.
 */
public final class PluginConfig {

    private final Path file;
    private final Map<String, String> values = new LinkedHashMap<>();

    public PluginConfig(Path dataDirectory) throws IOException {
        Files.createDirectories(dataDirectory);
        this.file = dataDirectory.resolve("config.toml");

        if (Files.notExists(file)) {
            copyDefault();
        }
        load();
    }

    private void copyDefault() throws IOException {
        try (InputStream in = getClass().getClassLoader().getResourceAsStream("config.toml")) {
            if (in != null) {
                Files.copy(in, file);
            } else {
                Files.writeString(file, "panel-url = \"\"\ntoken = \"\"\nserver-name = \"Proxy Velocity\"\nsync-interval = 60\n");
            }
        }
    }

    public void load() throws IOException {
        values.clear();
        for (String raw : Files.readAllLines(file, StandardCharsets.UTF_8)) {
            String line = raw.trim();
            if (line.isEmpty() || line.startsWith("#")) {
                continue;
            }
            int eq = line.indexOf('=');
            if (eq <= 0) {
                continue;
            }
            String key = line.substring(0, eq).trim();
            String value = line.substring(eq + 1).trim();
            // Retire les guillemets entourants pour les valeurs chaînes.
            if (value.length() >= 2 && value.startsWith("\"") && value.endsWith("\"")) {
                value = value.substring(1, value.length() - 1)
                    .replace("\\\"", "\"")
                    .replace("\\\\", "\\");
            }
            values.put(key, value);
        }
    }

    public String getString(String key, String def) {
        return values.getOrDefault(key, def);
    }

    public long getLong(String key, long def) {
        try {
            return Long.parseLong(getString(key, Long.toString(def)).trim());
        } catch (NumberFormatException e) {
            return def;
        }
    }

    public void set(String key, String value) {
        values.put(key, value);
    }

    /** Réécrit le fichier (valeurs chaînes entre guillemets, entiers bruts). */
    public synchronized void save() throws IOException {
        StringBuilder sb = new StringBuilder();
        sb.append("# Configuration de PixelCraftLink (proxy Velocity).\n");
        sb.append("# panel-url / token gérés automatiquement par « pixellink ».\n\n");
        for (Map.Entry<String, String> entry : values.entrySet()) {
            String value = entry.getValue();
            sb.append(entry.getKey()).append(" = ");
            if (isInteger(value)) {
                sb.append(value);
            } else {
                String escaped = value.replace("\\", "\\\\").replace("\"", "\\\"");
                sb.append('"').append(escaped).append('"');
            }
            sb.append('\n');
        }
        Files.writeString(file, sb.toString(), StandardCharsets.UTF_8);
    }

    private static boolean isInteger(String value) {
        if (value == null || value.isEmpty()) {
            return false;
        }
        for (int i = 0; i < value.length(); i++) {
            if (!Character.isDigit(value.charAt(i))) {
                return false;
            }
        }
        return true;
    }
}
