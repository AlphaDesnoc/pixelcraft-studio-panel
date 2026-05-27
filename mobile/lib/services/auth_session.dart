import 'package:flutter/material.dart';

import '../api/panel_api.dart';
import '../models/user.dart';

class AuthSession extends ChangeNotifier {
  AuthSession({PanelApi? api}) : _api = api ?? PanelApi();

  final PanelApi _api;

  PanelApi get api => _api;

  PanelUser? _user;
  bool _bootstrapping = true;

  PanelUser? get user => _user;
  bool get isAuthenticated => _user != null;
  bool get bootstrapping => _bootstrapping;

  Future<void> bootstrap() async {
    _bootstrapping = true;
    notifyListeners();

    try {
      if (await _api.client.hasToken()) {
        _user = await _api.fetchUser();
      }
    } catch (_) {
      await _api.client.clearToken();
      _user = null;
    } finally {
      _bootstrapping = false;
      notifyListeners();
    }
  }

  Future<void> completeLogin(PanelUser user) async {
    _user = user;
    notifyListeners();
  }

  Future<void> logout() async {
    await _api.logout();
    _user = null;
    notifyListeners();
  }

  Future<void> refreshUser() async {
    _user = await _api.fetchUser();
    notifyListeners();
  }
}
