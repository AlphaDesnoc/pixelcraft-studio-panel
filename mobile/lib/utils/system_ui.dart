import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Configure l'affichage edge-to-edge et les barres système Android/iOS.
Future<void> configureSystemUi() async {
  await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);

  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      systemNavigationBarColor: Colors.transparent,
      systemNavigationBarDividerColor: Colors.transparent,
    ),
  );
}

/// Padding bas sûr (barre de navigation Android 3 boutons, encoche, etc.).
double bottomSafePadding(BuildContext context) {
  return MediaQuery.viewPaddingOf(context).bottom;
}
