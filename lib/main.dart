import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'providers/auth_provider.dart';
import 'services/location_service.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/home/home_screen.dart';
import 'screens/wallet/balance_screen.dart';
import 'screens/wallet/transfer_screen.dart';
import 'screens/history/transactions_screen.dart';
import 'screens/profile/profile_screen.dart';
import 'screens/services/services_screen.dart';
import 'screens/services/cash_misr_payment_screen.dart';
import 'screens/wallet/deposits_report_screen.dart';
import 'screens/admin/deposit_methods_screen.dart';
import 'screens/admin/users_admin_screen.dart';
import 'screens/admin/admin_dashboard_screen.dart';
import 'screens/agent/agent_dashboard_screen.dart';
import 'screens/admin/services_admin_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // تهيئة Supabase من متغيرات بيئة (ضع القيم عبر --dart-define)
  final supabaseUrl = const String.fromEnvironment('SUPABASE_URL');
  final supabaseAnonKey = const String.fromEnvironment('SUPABASE_ANON_KEY');
  if (supabaseUrl.isNotEmpty && supabaseAnonKey.isNotEmpty) {
    await Supabase.initialize(url: supabaseUrl, anonKey: supabaseAnonKey);
  }

  // تحسين أداء الصور والرسوميات
  PaintingBinding.instance.imageCache.maximumSize =
      500; // تقليل عدد الصور المحفوظة
  PaintingBinding.instance.imageCache.maximumSizeBytes = 50 << 20; // 50MB

  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthProvider(),
      child: MaterialApp(
        title: 'تطبيق الدفع',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          primarySwatch: Colors.blue,
          textTheme: GoogleFonts.cairoTextTheme(Theme.of(context).textTheme),
          useMaterial3: true,
          visualDensity: VisualDensity.adaptivePlatformDensity,
          // تحسين الأداء
          pageTransitionsTheme: const PageTransitionsTheme(
            builders: <TargetPlatform, PageTransitionsBuilder>{
              TargetPlatform.android: FadeUpwardsPageTransitionsBuilder(),
              TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
            },
          ),
        ),
        home: const AuthWrapper(),
        routes: {
          '/login': (_) => const LoginScreen(),
          '/register': (_) => const RegisterScreen(),
          '/home': (_) => const HomeScreen(),
          '/agent': (_) => const AgentDashboardScreen(),
          '/balance': (_) => const BalanceScreen(),
          '/deposits_report': (_) => const DepositsReportScreen(),
          '/transfer': (_) => const TransferScreen(),
          '/transactions': (_) => const TransactionsScreen(),
          '/profile': (_) => const ProfileScreen(),
          '/services': (_) => const ServicesScreen(),
          '/cash_misr_payment': (_) => const CashMisrPaymentScreen(),
          '/admin/deposit_methods': (_) => const DepositMethodsScreen(),
          '/admin/users': (_) => const UsersAdminScreen(),
          '/admin': (_) => const AdminDashboardScreen(),
          '/admin/services': (_) => const ServicesAdminScreen(),
        },
      ),
    );
  }
}

/// يراقب حالة المصادقة ويعيد الشاشة المناسبة
/// يطلب صلاحية الموقع عند البدء
class AuthWrapper extends StatefulWidget {
  const AuthWrapper({super.key});

  @override
  State<AuthWrapper> createState() => _AuthWrapperState();
}

class _AuthWrapperState extends State<AuthWrapper> {
  Future<bool>? _locationReady;

  @override
  void initState() {
    super.initState();
    _locationReady = _ensureLocationReady();
  }

  Future<bool> _ensureLocationReady() async {
    final ok = await LocationService.instance.requestLocationPermission();
    final enabled = await LocationService.instance.isLocationServiceEnabled();
    return ok && enabled;
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return FutureBuilder<bool>(
      future: _locationReady,
      builder: (context, snap) {
        if (!snap.hasData) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        if (snap.data != true) {
          return Scaffold(
            appBar: AppBar(title: const Text('صلاحية الموقع مطلوبة')),
            body: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 24.0),
                    child: Text(
                      'لتشغيل التطبيق بشكل صحيح، نحتاج صلاحية الموقع (دائم) وتفعيل خدمات الموقع.',
                      textAlign: TextAlign.center,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton.icon(
                    onPressed: () {
                      setState(() {
                        _locationReady = _ensureLocationReady();
                      });
                    },
                    icon: const Icon(Icons.my_location),
                    label: const Text('إعادة المحاولة'),
                  ),
                ],
              ),
            ),
          );
        }

        if (!auth.isLoggedIn) {
          return const LoginScreen();
        }
        final user = auth.user;
        if (user == null) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        if (!user.isActive) {
          return Scaffold(
            body: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text('حسابك غير مفعل حالياً'),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () async {
                      await auth.signOut();
                      if (context.mounted) {
                        Navigator.pushNamedAndRemoveUntil(
                          context,
                          '/login',
                          (r) => false,
                        );
                      }
                    },
                    child: const Text('تسجيل خروج'),
                  ),
                ],
              ),
            ),
          );
        }
        if (user.role == 'admin') {
          return const AdminDashboardScreen();
        } else if (user.role == 'agent') {
          return const AgentDashboardScreen();
        }
        return const HomeScreen();
      },
    );
  }
}

// Note: use the real HomeScreen in screens/home/home_screen.dart
