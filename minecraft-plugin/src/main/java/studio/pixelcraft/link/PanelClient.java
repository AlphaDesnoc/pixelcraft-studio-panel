package studio.pixelcraft.link;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * Petit client HTTP sans dépendance externe pour parler à l'API plugin du panel.
 * Tous les appels sont bloquants : ils doivent être exécutés de façon asynchrone.
 */
public final class PanelClient {

    private final String baseUrl;
    private final String token;

    public PanelClient(String baseUrl, String token) {
        // Retire un éventuel slash final pour normaliser la construction des URL.
        this.baseUrl = baseUrl == null ? "" : baseUrl.replaceAll("/+$", "");
        this.token = token == null ? "" : token;
    }

    public boolean isConfigured() {
        return !baseUrl.isEmpty() && !token.isEmpty();
    }

    public Result post(String path, String jsonBody) {
        HttpURLConnection conn = null;
        try {
            URL url = URI.create(baseUrl + path).toURL();
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(8000);
            conn.setReadTimeout(8000);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("X-Server-Token", token);

            byte[] payload = jsonBody.getBytes(StandardCharsets.UTF_8);
            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload);
            }

            int status = conn.getResponseCode();
            String body = readBody(status < 400 ? conn.getInputStream() : conn.getErrorStream());
            return new Result(status, body);
        } catch (IOException e) {
            return new Result(-1, e.getMessage());
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }

    private static String readBody(InputStream stream) throws IOException {
        if (stream == null) {
            return "";
        }
        try (InputStream in = stream) {
            return new String(in.readAllBytes(), StandardCharsets.UTF_8);
        }
    }

    /**
     * Extrait la valeur d'un champ chaîne d'un JSON plat, sans dépendance.
     * Suffisant pour lire {"token":"..."} renvoyé par le panel.
     */
    public static String extractString(String json, String field) {
        if (json == null) {
            return null;
        }
        java.util.regex.Matcher m = java.util.regex.Pattern
            .compile("\"" + java.util.regex.Pattern.quote(field) + "\"\\s*:\\s*\"((?:\\\\.|[^\"\\\\])*)\"")
            .matcher(json);
        if (!m.find()) {
            return null;
        }
        return m.group(1)
            .replace("\\\"", "\"")
            .replace("\\\\", "\\");
    }

    /** Échappe une chaîne pour l'insérer dans un littéral JSON. */
    public static String escape(String value) {
        if (value == null) {
            return "";
        }
        StringBuilder sb = new StringBuilder(value.length() + 8);
        for (int i = 0; i < value.length(); i++) {
            char c = value.charAt(i);
            switch (c) {
                case '"' -> sb.append("\\\"");
                case '\\' -> sb.append("\\\\");
                case '\n' -> sb.append("\\n");
                case '\r' -> sb.append("\\r");
                case '\t' -> sb.append("\\t");
                default -> {
                    if (c < 0x20) {
                        sb.append(String.format("\\u%04x", (int) c));
                    } else {
                        sb.append(c);
                    }
                }
            }
        }
        return sb.toString();
    }

    public record Result(int status, String body) {
        public boolean ok() {
            return status >= 200 && status < 300;
        }
    }
}
