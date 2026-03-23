import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../services/auth_service.dart';
import '../services/supabase_data_service.dart';
import '../models/user_model.dart';

class AuthProvider with ChangeNotifier {
  SimpleAuthUser? _firebaseUser;
  UserModel? _userModel;

  AuthProvider() {
    AuthService.instance.authStateChanges.listen(_onSupabaseAuthChanged);
  }

  SimpleAuthUser? get firebaseUser => _firebaseUser;
  UserModel? get user => _userModel;

  bool get isLoggedIn => _firebaseUser != null;

  Future<void> _onSupabaseAuthChanged(Session? session) async {
    final su = session?.user;
    _firebaseUser =
        su == null ? null : SimpleAuthUser(uid: su.id, email: su.email);
    if (su != null) {
      _userModel = await SupabaseDataService.instance.getUser(su.id);
    } else {
      _userModel = null;
    }
    notifyListeners();
  }

  // مساعدات يمكن أن تستخدمها الواجهات
  Future<void> signOut() async {
    await AuthService.instance.signOut();
  }

  /// يتيح تحديث بيانات المستخدم محلياً (بعد نجاح التحديث في Firestore)
  void updateLocalUser(UserModel user) {
    _userModel = user;
    notifyListeners();
  }
}

class SimpleAuthUser {
  final String uid;
  final String? email;
  SimpleAuthUser({required this.uid, this.email});
}
