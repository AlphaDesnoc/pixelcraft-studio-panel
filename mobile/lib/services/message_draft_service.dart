import 'package:shared_preferences/shared_preferences.dart';

class MessageDraftService {
  MessageDraftService._();

  static Future<void> save(String key, String value) async {
    final prefs = await SharedPreferences.getInstance();
    if (value.trim().isEmpty) {
      await prefs.remove(key);
      return;
    }
    await prefs.setString(key, value);
  }

  static Future<String?> load(String key) async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(key);
  }

  static Future<void> clear(String key) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }

  static String projectChatKey(String slug, String space) =>
      'draft:chat:$slug:$space';

  static String directMessageKey(int conversationId) =>
      'draft:dm:$conversationId';
}
