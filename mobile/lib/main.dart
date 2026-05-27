import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';

// Extensions loaded app-wide for PanelApi.
// ignore: unused_import
import 'api/panel_api_extensions.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'services/app_update_service.dart';
import 'services/auth_session.dart';
import 'services/realtime_service.dart';
import 'theme/app_theme.dart';
import 'utils/system_ui.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('fr_FR');
  await configureSystemUi();
  runApp(const PixelCraftPanelApp());
}

class PixelCraftPanelApp extends StatelessWidget {
  const PixelCraftPanelApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthSession()..bootstrap()),
        ChangeNotifierProvider(create: (_) => RealtimeService()),
      ],
      child: const _AppRoot(),
    );
  }
}

class _AppRoot extends StatefulWidget {
  const _AppRoot();

  @override
  State<_AppRoot> createState() => _AppRootState();
}

class _AppRootState extends State<_AppRoot> {
  AuthSession? _session;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _session = context.read<AuthSession>();
      final realtime = context.read<RealtimeService>();
      if (_session!.isAuthenticated) {
        realtime.start();
      }
      _session!.addListener(_onSessionChanged);
    });
  }

  void _onSessionChanged() {
    if (_session == null || !mounted) return;
    final realtime = context.read<RealtimeService>();
    if (_session!.isAuthenticated) {
      realtime.start();
    } else {
      realtime.stop();
    }
    setState(() {});
  }

  @override
  void dispose() {
    _session?.removeListener(_onSessionChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<AuthSession>();
    final platformBrightness = MediaQuery.platformBrightnessOf(context);
    final themePreference = session.user?.themePreference ?? 'dark';

    return MaterialApp(
      title: 'PixelCraft Panel',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.themeFor(themePreference, platformBrightness),
      home: const _RootScreen(),
    );
  }
}

class _RootScreen extends StatefulWidget {
  const _RootScreen();

  @override
  State<_RootScreen> createState() => _RootScreenState();
}

class _RootScreenState extends State<_RootScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      AppUpdateService.checkOnStartup(context);
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<AuthSession>();

    if (session.bootstrapping) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (session.isAuthenticated) {
      return const HomeScreen();
    }

    return const LoginScreen();
  }
}
