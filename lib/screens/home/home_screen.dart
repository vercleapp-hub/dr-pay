// lib/screens/home/home_screen.dart
// شاشة رئيسية تعرض الرصيد والخدمات بتصميم محسّن

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/supabase_data_service.dart';
import '../../models/wallet_model.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late GlobalKey<RefreshIndicatorState> _refreshIndicatorKey;

  @override
  void initState() {
    super.initState();
    _refreshIndicatorKey = GlobalKey<RefreshIndicatorState>();
  }

  // بطاقة خدمة
  Widget _buildServiceCard(
    IconData icon,
    String title,
    String description,
    String route,
  ) {
    return RepaintBoundary(
      child: GestureDetector(
        onTap: () => Navigator.pushNamed(context, route),
        child: Card(
          elevation: 2,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              color: Colors.white,
            ),
            padding: const EdgeInsets.all(12.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 36, color: Colors.blue),
                const SizedBox(height: 8),
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  description,
                  style: const TextStyle(
                    fontSize: 11,
                    color: Colors.grey,
                    height: 1.3,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _refreshBalance() async {
    // إعادة تحميل البيانات
    await Future.delayed(const Duration(milliseconds: 500));
    if (mounted) {
      setState(() {});
    }
  }

  Widget _buildBalanceSection() {
    final userId = context.watch<AuthProvider>().firebaseUser?.uid;
    if (userId == null) {
      return const Text('...');
    }

    return StreamBuilder<WalletModel?>(
      stream: SupabaseDataService.instance.walletStream(userId),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return const Text('خطأ في المحفظة');
        }
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const CircularProgressIndicator();
        }
        final wallet = snapshot.data;
        final amountText = wallet == null
            ? '0.00'
            : wallet.balance.toStringAsFixed(2);
        final currencyText = wallet?.currency ?? 'EGP';
        return GestureDetector(
          onTap: () async {
            // عند الضغط على الرصيد - عمل تحديث يدوي
            await _refreshBalance();
            if (!context.mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('تم تحديث الرصيد'),
                duration: Duration(seconds: 2),
              ),
            );
          },
          child: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [Colors.blue.shade400, Colors.blue.shade700],
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            padding: const EdgeInsets.all(24.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'الرصيد الحالي',
                      style: TextStyle(color: Colors.white70, fontSize: 14),
                    ),
                    Icon(Icons.refresh, color: Colors.white70, size: 18),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  '$amountText $currencyText',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 36,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final screenWidth = MediaQuery.of(context).size.width;
    final userModel = auth.user;
    final firstName = userModel != null
        ? userModel.fullName.split(' ').first
        : (auth.firebaseUser?.email ?? 'مستخدم');
    final isAdmin = (userModel?.role == 'admin');

    return Scaffold(
      appBar: AppBar(
        title: const Text('الصفحة الرئيسية'),
        elevation: 0,
        backgroundColor: Colors.blue,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'تسجيل الخروج',
            onPressed: () async {
              await auth.signOut();
              if (!context.mounted) return;
              Navigator.pushNamedAndRemoveUntil(
                context,
                '/login',
                (route) => false,
              );
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        key: _refreshIndicatorKey,
        onRefresh: _refreshBalance,
        child: SingleChildScrollView(
          padding: EdgeInsets.symmetric(
            horizontal: screenWidth > 600 ? 32 : 16,
            vertical: 16,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // التحية
              Text(
                'مرحباً، $firstName',
                style: TextStyle(
                  fontSize: screenWidth > 600 ? 28 : 24,
                  fontWeight: FontWeight.bold,
                ),
              ),
              SizedBox(height: screenWidth > 600 ? 28 : 20),

              // الرصيد
              _buildBalanceSection(),
              SizedBox(height: screenWidth > 600 ? 40 : 30),

              // شبكة الخدمات
              GridView.count(
                crossAxisCount: screenWidth > 600 ? 3 : 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
                childAspectRatio: 1.05,
                children: [
                  if (isAdmin)
                    _buildServiceCard(
                      Icons.dashboard,
                      'لوحة الإدارة',
                      'إدارة النظام',
                      '/admin',
                    ),
                  _buildServiceCard(
                    Icons.shopping_bag,
                    'الخدمات',
                    'دفع فواتير، شحن رصيد',
                    '/services',
                  ),
                  _buildServiceCard(
                    Icons.send,
                    'تحويل',
                    'بين حسابات المستخدمين',
                    '/transfer',
                  ),
                  _buildServiceCard(
                    Icons.history,
                    'المعاملات',
                    'سجل جميع عملياتك',
                    '/transactions',
                  ),
                  _buildServiceCard(
                    Icons.account_balance_wallet,
                    'إيداع',
                    'شحن الحساب',
                    '/balance',
                  ),
                  _buildServiceCard(
                    Icons.receipt_long,
                    'سجل الإيداعات',
                    'سجل جميع إيداعاتك',
                    '/deposits_report',
                  ),
                  _buildServiceCard(
                    Icons.settings,
                    'الإعدادات',
                    'تخصيص حسابك',
                    '/profile',
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
