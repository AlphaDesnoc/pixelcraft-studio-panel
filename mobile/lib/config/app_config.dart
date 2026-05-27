class AppConfig {
  static const defaultPanelBaseUrl = 'https://panel.pixelcraft-studios.fr';

  static const deviceName = 'PixelCraft Panel Mobile';

  static const mobileUserAgentSuffix = 'PixelCraftPanelMobile/1.0';

  /// Override: `--dart-define=PANEL_BASE_URL=http://10.0.2.2:8000`
  static String get panelBaseUrl {
    const fromEnv = String.fromEnvironment('PANEL_BASE_URL');
    if (fromEnv.isNotEmpty) {
      return fromEnv.endsWith('/')
          ? fromEnv.substring(0, fromEnv.length - 1)
          : fromEnv;
    }
    return defaultPanelBaseUrl;
  }

  static String get apiBaseUrl => '$panelBaseUrl/api/v1';
}
