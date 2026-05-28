import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Mirrors web [useFocusMode.js] — persisted focus mode for distraction-free Kanban.
class FocusModeService extends ChangeNotifier {
  static const _storageKey = 'panel:focus-mode';

  bool _enabled = false;
  bool _loaded = false;

  bool get isFocusMode => _enabled;
  bool get isLoaded => _loaded;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _enabled = prefs.getString(_storageKey) == '1';
    _loaded = true;
    notifyListeners();
  }

  Future<void> toggle() async {
    await setFocusMode(!_enabled);
  }

  Future<void> setFocusMode(bool value) async {
    _enabled = value;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_storageKey, value ? '1' : '0');
  }
}
