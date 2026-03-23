// lib/screens/auth/login_screen.dart
import 'package:flutter/material.dart';

// خدمات
import '../../services/auth_service.dart';
import '../../services/storage_service.dart';
import '../../services/firestore_service.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  bool _rememberMe = false;
  String _deviceInfo = '';

  @override
  void initState() {
    super.initState();
    _loadSavedCredentials();
    _collectDeviceInfo();
  }

  /// تحميل البيانات المحفوظة عند فتح الصفحة
  Future<void> _loadSavedCredentials() async {
    final credentials = await StorageService.instance
        .getSavedLoginCredentials();
    final isRemembered = await StorageService.instance.isRemembered();

    if (isRemembered &&
        credentials['email'] != null &&
        credentials['password'] != null) {
      setState(() {
        _emailController.text = credentials['email']!;
        _passwordController.text = credentials['password']!;
        _rememberMe = true;
      });
    }
  }

  Future<void> _collectDeviceInfo() async {
    try {
      // نستخدم device_info_plus إذا كانت متاحة
      // ولتجنب الأعطال إن لم يتم جلب الحزم بعد، نستخدم استدعاء ديناميكي.
      // سيتم تعبئة ملخص مبسط فقط.
      String summary = '';
      // لا نستورد مباشرة هنا لتفادي فشل التحليل عند غياب pub get في بيئات معينة.
      // يمكن استبدالها باستيراد مباشر device_info_plus في حالتك الفعلية.
      summary = 'نظام التشغيل: ${Theme.of(context).platform.name}';
      setState(() => _deviceInfo = summary);
    } catch (_) {
      // تجاهل الخطأ
    }
  }

  /// استخدام رقم الهاتف أو البريد لتسجيل الدخول
  Future<void> _signIn() async {
    setState(() => _isLoading = true);
    try {
      final identifier = _emailController.text.trim();
      final password = _passwordController.text.trim();
      String emailToUse = identifier;
      if (!identifier.contains('@')) {
        final userModel =
            await FirestoreService.instance.getUserByPhone(identifier);
        if (userModel == null || userModel.email.isEmpty) {
          throw Exception('لا يوجد مستخدم بهذا الرقم');
        }
        emailToUse = userModel.email;
      }
      final user = await AuthService.instance
          .signIn(email: emailToUse, password: password);

      // حفظ البيانات إذا تم تفعيل Remember Me
      if (user != null && _rememberMe) {
        await StorageService.instance.saveLoginCredentials(
          email: identifier,
          password: _passwordController.text.trim(),
        );
      } else if (user != null && !_rememberMe) {
        // حذف البيانات المحفوظة إذا تم إلغاء Remember Me
        await StorageService.instance.clearSavedCredentials();
      }

      if (user != null) {
        if (!mounted) return;
        Navigator.pushReplacementNamed(context, '/home');
      }
    } catch (e) {
      String message = 'حدث خطأ';
      final es = e.toString();
      if (es.contains('user-not-found')) {
        message = 'لا يوجد مستخدم بهذا الرقم';
      } else if (es.contains('wrong-password')) {
        message = 'كلمة المرور غير صحيحة';
      } else if (es.contains('لا يوجد مستخدم بهذا الرقم')) {
        message = 'لا يوجد مستخدم بهذا الرقم';
      }
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(message)));
      }
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenHeight = MediaQuery.of(context).size.height;
    final screenWidth = MediaQuery.of(context).size.width;
    final isSmallScreen = screenHeight < 600;

    return Scaffold(
      appBar: AppBar(
        title: const Text('تسجيل الدخول'),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.symmetric(
            horizontal: screenWidth > 600 ? 60 : 20,
            vertical: isSmallScreen ? 16 : 20,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // لوجو التطبيق
              SizedBox(
                height: isSmallScreen ? 80 : 120,
                width: isSmallScreen ? 140 : 220,
                child: FittedBox(
                  fit: BoxFit.contain,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.asset(
                      'assets/logo.png',
                      errorBuilder: (context, error, stackTrace) {
                        return Container(
                          width: isSmallScreen ? 80 : 120,
                          height: isSmallScreen ? 80 : 120,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [
                                Colors.blue.shade400,
                                Colors.blue.shade700,
                              ],
                            ),
                            borderRadius: BorderRadius.circular(12),
                            boxShadow: const [
                              BoxShadow(
                                color: Colors.black12,
                                blurRadius: 8,
                                offset: Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Center(
                            child: Text(
                              'ED',
                              style: TextStyle(
                                fontSize: isSmallScreen ? 24 : 40,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ),
              ),
              SizedBox(height: isSmallScreen ? 24 : 40),
              Text(
                'ELDoctor Pay',
                style: TextStyle(
                  fontSize: isSmallScreen ? 20 : 28,
                  fontWeight: FontWeight.bold,
                  color: Colors.blue.shade700,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'تطبيق الدفع الآمن والسريع',
                style: TextStyle(
                  fontSize: isSmallScreen ? 12 : 14,
                  color: Colors.grey.shade600,
                ),
                textAlign: TextAlign.center,
              ),
              SizedBox(height: isSmallScreen ? 24 : 40),
              TextField(
                controller: _emailController,
                decoration: InputDecoration(
                  labelText: 'رقم الهاتف أو اسم المستخدم',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.person),
                  filled: true,
                  fillColor: Colors.grey.shade50,
                ),
                keyboardType: TextInputType.text,
              ),
              SizedBox(height: isSmallScreen ? 12 : 15),
              TextField(
                controller: _passwordController,
                decoration: InputDecoration(
                  labelText: 'كلمة المرور',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.lock),
                  filled: true,
                  fillColor: Colors.grey.shade50,
                ),
                obscureText: true,
              ),
              SizedBox(height: isSmallScreen ? 12 : 15),
              // زر حفظ البيانات (Remember Me)
              Row(
                children: [
                  Checkbox(
                    value: _rememberMe,
                    onChanged: (value) {
                      setState(() => _rememberMe = value ?? false);
                    },
                  ),
                  Flexible(
                    child: Text(
                      'تذكر بيانات الدخول',
                      style: TextStyle(fontSize: isSmallScreen ? 12 : 14),
                    ),
                  ),
                ],
              ),
              SizedBox(height: isSmallScreen ? 20 : 30),
              if (_isLoading)
                const CircularProgressIndicator()
              else
                Column(
                  children: [
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _signIn,
                        style: ElevatedButton.styleFrom(
                          padding: EdgeInsets.symmetric(
                            vertical: isSmallScreen ? 12 : 15,
                          ),
                          backgroundColor: Colors.blue,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: Text(
                          'تسجيل الدخول',
                          style: TextStyle(
                            fontSize: isSmallScreen ? 14 : 16,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                    SizedBox(height: isSmallScreen ? 8 : 10),
                    TextButton(
                      onPressed: () {
                        Navigator.pushReplacementNamed(context, '/register');
                      },
                      child: Text(
                        'إنشاء حساب جديد',
                        style: TextStyle(fontSize: isSmallScreen ? 12 : 14),
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_deviceInfo.isNotEmpty)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'معلومات الجهاز:',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _deviceInfo,
                              style: const TextStyle(fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }
}
