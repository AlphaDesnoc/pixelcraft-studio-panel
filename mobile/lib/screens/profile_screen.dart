import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/panel_api_extensions.dart';
import '../services/auth_session.dart';
import '../services/realtime_service.dart';
import '../utils/format.dart';
import 'login_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, bool> _preferences = {};
  Map<String, String> _labels = {};
  bool _loadingPrefs = true;

  final _currentPasswordController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmController = TextEditingController();
  final _twoFactorCodeController = TextEditingController();

  List<String>? _recoveryCodes;

  @override
  void initState() {
    super.initState();
    _loadPreferences();
  }

  @override
  void dispose() {
    _currentPasswordController.dispose();
    _passwordController.dispose();
    _passwordConfirmController.dispose();
    _twoFactorCodeController.dispose();
    super.dispose();
  }

  Future<void> _loadPreferences() async {
    try {
      final data = await context.read<AuthSession>().api.fetchNotificationPreferences();
      if (!mounted) return;
      setState(() {
        _preferences = data.preferences;
        _labels = data.labels;
        _loadingPrefs = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingPrefs = false);
    }
  }

  Future<void> _togglePref(String key, bool value) async {
    final next = Map<String, bool>.from(_preferences)..[key] = value;
    setState(() => _preferences = next);
    await context.read<AuthSession>().api.updateNotificationPreferences(next);
  }

  Future<void> _changePassword() async {
    try {
      await context.read<AuthSession>().api.updatePassword(
            currentPassword: _currentPasswordController.text,
            password: _passwordController.text,
            passwordConfirmation: _passwordConfirmController.text,
          );
      if (!mounted) return;
      _currentPasswordController.clear();
      _passwordController.clear();
      _passwordConfirmController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Mot de passe mis à jour')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _changeTheme(String theme) async {
    await context.read<AuthSession>().api.updateTheme(theme);
    await context.read<AuthSession>().refreshUser();
  }

  Future<void> _setupTwoFactor() async {
    try {
      final uri = await context.read<AuthSession>().api.setupTwoFactor();
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Configurer la 2FA'),
          content: SelectableText(uri),
          actions: [
            FilledButton(onPressed: () => Navigator.pop(context), child: const Text('OK')),
          ],
        ),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _confirmTwoFactor() async {
    try {
      final codes = await context.read<AuthSession>().api.confirmTwoFactor(
            _twoFactorCodeController.text.trim(),
          );
      await context.read<AuthSession>().refreshUser();
      if (!mounted) return;
      setState(() => _recoveryCodes = codes);
      _twoFactorCodeController.clear();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _disableTwoFactor() async {
    await context.read<AuthSession>().api.disableTwoFactor();
    await context.read<AuthSession>().refreshUser();
    if (!mounted) return;
    setState(() => _recoveryCodes = null);
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthSession>().user;
    if (user == null) return const SizedBox.shrink();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(radius: 28, child: Text(initialsFromName(user.name))),
                const SizedBox(height: 12),
                Text(user.name, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 4),
                Text(user.email),
                const SizedBox(height: 8),
                Text('Rôle : ${user.role}${user.isAdmin ? ' (admin)' : ''}'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text('Thème', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'light', label: Text('Clair')),
            ButtonSegment(value: 'dark', label: Text('Sombre')),
            ButtonSegment(value: 'system', label: Text('Système')),
          ],
          selected: {user.themePreference},
          onSelectionChanged: (value) => _changeTheme(value.first),
        ),
        const SizedBox(height: 24),
        Text('Mot de passe', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        TextField(
          controller: _currentPasswordController,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Mot de passe actuel'),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _passwordController,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Nouveau mot de passe'),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _passwordConfirmController,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Confirmation'),
        ),
        const SizedBox(height: 12),
        FilledButton(onPressed: _changePassword, child: const Text('Mettre à jour le mot de passe')),
        const SizedBox(height: 24),
        Text('Double authentification', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        Text(user.twoFactorEnabled ? '2FA activée' : '2FA désactivée'),
        if (!user.twoFactorEnabled) ...[
          const SizedBox(height: 8),
          OutlinedButton(onPressed: _setupTwoFactor, child: const Text('Configurer la 2FA')),
          const SizedBox(height: 8),
          TextField(
            controller: _twoFactorCodeController,
            decoration: const InputDecoration(labelText: 'Code de confirmation'),
          ),
          FilledButton(onPressed: _confirmTwoFactor, child: const Text('Activer la 2FA')),
        ] else
          OutlinedButton(onPressed: _disableTwoFactor, child: const Text('Désactiver la 2FA')),
        if (_recoveryCodes != null) ...[
          const SizedBox(height: 12),
          Text('Codes de récupération', style: Theme.of(context).textTheme.titleSmall),
          ..._recoveryCodes!.map((c) => Text(c)),
        ],
        const SizedBox(height: 24),
        Text('Notifications', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        if (_loadingPrefs)
          const Center(child: CircularProgressIndicator())
        else
          ..._labels.entries.map(
            (entry) => SwitchListTile(
              title: Text(entry.value),
              value: _preferences[entry.key] ?? true,
              onChanged: (value) => _togglePref(entry.key, value),
            ),
          ),
        const SizedBox(height: 24),
        OutlinedButton(
          onPressed: () async {
            context.read<RealtimeService>().stop();
            await context.read<AuthSession>().logout();
            if (!context.mounted) return;
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const LoginScreen()),
              (_) => false,
            );
          },
          child: const Text('Se déconnecter'),
        ),
      ],
    );
  }
}
