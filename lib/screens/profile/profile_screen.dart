// lib/screens/profile/profile_screen.dart
// شاشة الملف الشخصي للمستخدم مع إمكانية تعديل البيانات

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:supabase_flutter/supabase_flutter.dart' as sb;

import '../../providers/auth_provider.dart';
import '../../models/user_model.dart';
import '../../services/supabase_data_service.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late TextEditingController _nameController;
  late TextEditingController _phoneController;
  late TextEditingController _emailController;
  late TextEditingController _accountController;
  late TextEditingController _addressController;
  late TextEditingController _nidController;
  bool _isLoading = false;
  final _oldPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameController = TextEditingController(text: user?.fullName ?? '');
    _phoneController = TextEditingController(text: user?.phoneNumber ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
    _accountController = TextEditingController(text: user?.accountNumber ?? '');
    _addressController = TextEditingController(text: user?.address ?? '');
    _nidController = TextEditingController(text: user?.nationalId ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _accountController.dispose();
    _addressController.dispose();
    _nidController.dispose();
    _oldPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _isLoading = true);

    final provider = context.read<AuthProvider>();
    final current = provider.user;
    if (current != null) {
      final newEmail = _emailController.text.trim();
      if (newEmail.isNotEmpty && newEmail != current.email) {
        try {
          await sb.Supabase.instance.client.auth
              .updateUser(sb.UserAttributes(email: newEmail));
        } catch (e) {
          if (mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text('تعذّر تحديث البريد: $e')));
          }
        }
      }
      final updated = UserModel(
        userId: current.userId,
        email: newEmail.isNotEmpty ? newEmail : current.email,
        fullName: _nameController.text.trim(),
        phoneNumber: _phoneController.text.trim(),
        nationalId: _nidController.text.trim(),
        address: _addressController.text.trim(),
        accountNumber: _accountController.text.trim(),
        idPhotoFront: current.idPhotoFront,
        idPhotoBack: current.idPhotoBack,
        latitude: current.latitude,
        longitude: current.longitude,
        createdAt: current.createdAt,
        isActive: current.isActive,
      );
      await SupabaseDataService.instance.updateUser(updated);
      // نحدث المزود
      provider.updateLocalUser(updated);
    }

    if (!mounted) return;
    setState(() => _isLoading = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('تم حفظ التعديلات')));
  }

  Future<void> _sendPasswordReset() async {
    final email = _emailController.text.trim();
    if (email.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى إدخال البريد الإلكتروني')),
      );
      return;
    }
    try {
      await sb.Supabase.instance.client.auth
          .resetPasswordForEmail(email, redirectTo: null);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم إرسال رابط تغيير كلمة المرور')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('تعذّر الإرسال: $e')));
    }
  }

  Future<void> _logout() async {
    await context.read<AuthProvider>().signOut();
    if (!mounted) return;
    Navigator.pushNamedAndRemoveUntil(context, '/login', (route) => false);
  }

  Future<void> _changePassword() async {
    final oldPass = _oldPasswordController.text;
    final newPass = _newPasswordController.text;
    final confirm = _confirmPasswordController.text;
    if (oldPass.isEmpty || newPass.isEmpty || newPass != confirm) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تحقق من كلمة المرور القديمة والجديدة')),
      );
      return;
    }
    try {
      await sb.Supabase.instance.client.auth
          .updateUser(sb.UserAttributes(password: newPass));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تغيير كلمة المرور بنجاح')),
      );
      _oldPasswordController.clear();
      _newPasswordController.clear();
      _confirmPasswordController.clear();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تعذّر تغيير كلمة المرور: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = context.watch<AuthProvider>().user;
    return Scaffold(
      appBar: AppBar(
        title: const Text('الملف الشخصي'),
        actions: [
          IconButton(
            onPressed: _logout,
            icon: const Icon(Icons.logout),
            tooltip: 'تسجيل الخروج',
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (u != null) ...[
              Text('رقم الحساب: ${u.accountNumber}'),
              const SizedBox(height: 8),
              Text('الحالة: ${u.isActive ? 'نشط' : 'غير نشط'}'),
              const SizedBox(height: 8),
              Text('تاريخ الإنشاء: ${u.createdAt.toLocal()}'),
              const Divider(height: 32),
            ],
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(labelText: 'الاسم الكامل'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'البريد الإلكتروني'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'رقم الهاتف'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _accountController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'رقم الحساب'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _nidController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'الرقم القومي'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _addressController,
              decoration: const InputDecoration(labelText: 'العنوان'),
            ),
            const SizedBox(height: 20),
            if (_isLoading)
              const Center(child: CircularProgressIndicator())
            else
              Column(
                children: [
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _save,
                      child: const Text('حفظ التعديلات'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: _sendPasswordReset,
                      child: const Text('إرسال رابط تغيير كلمة المرور'),
                    ),
                  ),
                  const Divider(height: 32),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'تغيير كلمة المرور داخل التطبيق',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _oldPasswordController,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'كلمة المرور الحالية',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _newPasswordController,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'كلمة المرور الجديدة',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _confirmPasswordController,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'تأكيد كلمة المرور الجديدة',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _changePassword,
                      icon: const Icon(Icons.lock_reset),
                      label: const Text('تغيير كلمة المرور'),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
