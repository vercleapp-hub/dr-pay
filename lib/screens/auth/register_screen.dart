// lib/screens/auth/register_screen.dart
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:convert';

import '../../services/auth_service.dart';
import '../../services/supabase_data_service.dart';
import '../../services/location_service.dart';
import '../../models/user_model.dart';
import '../../models/wallet_model.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _nameController = TextEditingController();
  final _nationalIdController = TextEditingController();
  final _phoneController = TextEditingController();
  final _addressController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final ImagePicker _imagePicker = ImagePicker();

  String? _idPhotoFront; // Base64
  String? _idPhotoBack; // Base64
  double? _latitude;
  double? _longitude;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    // طلب صلاحية الموقع وجلبه تلقائياً عند فتح الصفحة
    _requestLocationOnInit();
  }

  Future<void> _requestLocationOnInit() async {
    try {
      final position = await LocationService.instance.getCurrentLocation();
      if (position != null && mounted) {
        setState(() {
          _latitude = position.latitude;
          _longitude = position.longitude;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'تم جلب الموقع: ${position.latitude.toStringAsFixed(4)}, ${position.longitude.toStringAsFixed(4)}',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('خطأ في جلب الموقع: $e')));
      }
    }
  }

  /// اختيار صورة من الجهاز
  Future<void> _pickImage(bool isFront) async {
    try {
      final image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxHeight: 800,
        maxWidth: 600,
      );

      if (image != null) {
        final bytes = await image.readAsBytes();
        final base64String = base64Encode(bytes);

        setState(() {
          if (isFront) {
            _idPhotoFront = base64String;
          } else {
            _idPhotoBack = base64String;
          }
        });

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                isFront
                    ? 'تم رفع صورة البطاقة (الوجه)'
                    : 'تم رفع صورة البطاقة (الظهر)',
              ),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('خطأ في رفع الصورة: $e')));
        setState(() => _isLoading = false);
      }
    }
  }

  /// تسجيل الحساب
  Future<void> _register() async {
    // التحقق من الحقول
    if (_nameController.text.trim().isEmpty ||
        _nationalIdController.text.trim().isEmpty ||
        _phoneController.text.trim().isEmpty ||
        _addressController.text.trim().isEmpty ||
        _emailController.text.trim().isEmpty ||
        _passwordController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى ملء جميع الحقول المطلوبة')),
      );
      return;
    }

    if (_passwordController.text != _confirmController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('كلمتا المرور غير متطابقتين')),
      );
      return;
    }

    if (_idPhotoFront == null || _idPhotoBack == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى رفع صور البطاقة (الوجه والظهر)')),
      );
      return;
    }

    setState(() => _isLoading = true);
    try {
      final user = await AuthService.instance.signUp(
        email: _emailController.text.trim(),
        password: _passwordController.text.trim(),
      );

      if (user != null) {
        if (!mounted) return;

        final accountNumber = _generateAccountNumber();
        final userModel = UserModel(
          userId: user.id,
          email: user.email ?? '',
          fullName: _nameController.text.trim(),
          phoneNumber: _phoneController.text.trim(),
          nationalId: _nationalIdController.text.trim(),
          address: _addressController.text.trim(),
          accountNumber: accountNumber,
          idPhotoFront: _idPhotoFront,
          idPhotoBack: _idPhotoBack,
          latitude: _latitude,
          longitude: _longitude,
          createdAt: DateTime.now(),
        );

        await SupabaseDataService.instance.createUser(userModel);

        final walletModel = WalletModel(
          walletId: user.id,
          userId: user.id,
          lastUpdated: DateTime.now(),
        );
        await SupabaseDataService.instance.createWallet(walletModel);

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('تم إنشاء الحساب بنجاح'),
            ),
          );
          Navigator.pushReplacementNamed(context, '/login');
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('حدث خطأ: ${e.toString()}')));
      }
    } finally {
      setState(() => _isLoading = false);
    }
  }

  String _generateAccountNumber() {
    // رقم حساب عشوائي من 4 أرقام
    return (1000 + (DateTime.now().microsecond % 9000)).toString();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('إنشاء حساب جديد'),
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // الاسم الكامل
            const Text(
              'الاسم بالكامل',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(
                hintText: 'أدخل الاسم ثلاثي كما في البطاقة',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.person),
              ),
            ),
            const SizedBox(height: 20),

            // الرقم القومي
            const Text(
              'الرقم القومي',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _nationalIdController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                hintText: 'أدخل الرقم القومي',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.card_membership),
              ),
            ),
            const SizedBox(height: 20),

            // رقم المحمول
            const Text(
              'رقم المحمول',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                hintText: 'أدخل رقم المحمول',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.phone),
              ),
            ),
            const SizedBox(height: 20),

            // العنوان بالتفصيل
            const Text(
              'العنوان بالتفصيل',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _addressController,
              maxLines: 3,
              decoration: const InputDecoration(
                hintText: 'أدخل العنوان بالتفصيل',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.location_on),
              ),
            ),
            const SizedBox(height: 20),

            // البريد الإلكتروني
            const Text(
              'البريد الإلكتروني',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                hintText: 'أدخل بريدك الإلكتروني',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.email),
              ),
            ),
            const SizedBox(height: 20),

            // كلمة المرور
            const Text(
              'كلمة المرور',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _passwordController,
              obscureText: true,
              decoration: const InputDecoration(
                hintText: 'أدخل كلمة المرور',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.lock),
              ),
            ),
            const SizedBox(height: 20),

            // تأكيد كلمة المرور
            const Text(
              'تأكيد كلمة المرور',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _confirmController,
              obscureText: true,
              decoration: const InputDecoration(
                hintText: 'أعد إدخال كلمة المرور',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.lock),
              ),
            ),
            const SizedBox(height: 30),

            // صورة البطاقة (الوجه)
            const Text(
              'صورة البطاقة (الوجه)',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                children: [
                  if (_idPhotoFront != null)
                    const Text(
                      '✓ تم رفع صورة الوجه',
                      style: TextStyle(color: Colors.green),
                    )
                  else
                    const Text('انقر أو اسحب لرفع صورة وجه البطاقة'),
                  const SizedBox(height: 8),
                  const Text(
                    'JPG - الحد الأقصى 5MB',
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton.icon(
                    onPressed: () => _pickImage(true),
                    icon: const Icon(Icons.image),
                    label: const Text('اختر صورة'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // صورة البطاقة (الظهر)
            const Text(
              'صورة البطاقة (الظهر)',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                children: [
                  if (_idPhotoBack != null)
                    const Text(
                      '✓ تم رفع صورة الظهر',
                      style: TextStyle(color: Colors.green),
                    )
                  else
                    const Text('انقر أو اسحب لرفع صورة ظهر البطاقة'),
                  const SizedBox(height: 8),
                  const Text(
                    'JPG - الحد الأقصى 5MB',
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton.icon(
                    onPressed: () => _pickImage(false),
                    icon: const Icon(Icons.image),
                    label: const Text('اختر صورة'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 30),

            // زر إنشاء الحساب
            if (_isLoading)
              const Center(child: CircularProgressIndicator())
            else
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _register,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 15),
                  ),
                  child: const Text('إنشاء الحساب'),
                ),
              ),
            const SizedBox(height: 20),
            Center(
              child: TextButton(
                onPressed: () {
                  Navigator.pushReplacementNamed(context, '/login');
                },
                child: const Text('لديك حساب بالفعل؟ تسجيل الدخول'),
              ),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _nameController.dispose();
    _nationalIdController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }
}
