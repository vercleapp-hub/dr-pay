import 'package:supabase_flutter/supabase_flutter.dart';

class AuthService {
  AuthService._privateConstructor();
  static final AuthService instance = AuthService._privateConstructor();

  SupabaseClient get _client => Supabase.instance.client;

  /// يقوم بتسجيل الدخول باستخدام البريد أو الهاتف مع كلمة المرور
  Future<User?> signIn({
    required String email,
    required String password,
  }) async {
    final identifier = email.trim();
    if (identifier.contains('@')) {
      final res = await _client.auth.signInWithPassword(
        email: identifier,
        password: password,
      );
      return res.user;
    } else {
      final res = await _client.auth.signInWithPassword(
        phone: identifier,
        password: password,
      );
      return res.user;
    }
  }

  /// إنشاء مستخدم جديد (بريد + كلمة مرور)
  Future<User?> signUp({
    required String email,
    required String password,
  }) async {
    final res = await _client.auth.signUp(email: email, password: password);
    return res.user;
  }

  /// يخرج المستخدم الحالي
  Future<void> signOut() async {
    await _client.auth.signOut();
  }

  /// مراقبة حالة تسجيل الدخول
  Stream<Session?> get authStateChanges =>
      _client.auth.onAuthStateChange.map((e) => e.session);
}
