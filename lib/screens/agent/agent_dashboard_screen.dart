import 'package:flutter/material.dart';

class AgentDashboardScreen extends StatelessWidget {
  const AgentDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('لوحة الوكيل')),
      body: GridView.count(
        crossAxisCount: MediaQuery.of(context).size.width > 600 ? 3 : 2,
        padding: const EdgeInsets.all(16),
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
        children: [
          _buildCard(
            context,
            icon: Icons.person_search,
            title: 'بحث مستخدم',
            onTap: () => Navigator.pushNamed(context, '/transfer'),
          ),
          _buildCard(
            context,
            icon: Icons.account_balance_wallet,
            title: 'شحن رصيد',
            onTap: () => Navigator.pushNamed(context, '/balance'),
          ),
          _buildCard(
            context,
            icon: Icons.receipt_long,
            title: 'سجل العمليات',
            onTap: () => Navigator.pushNamed(context, '/transactions'),
          ),
          _buildCard(
            context,
            icon: Icons.settings,
            title: 'الإعدادات',
            onTap: () => Navigator.pushNamed(context, '/profile'),
          ),
        ],
      ),
    );
  }

  Widget _buildCard(BuildContext context,
      {required IconData icon, required String title, required VoidCallback onTap}) {
    return Card(
      elevation: 2,
      child: InkWell(
        onTap: onTap,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 40, color: Colors.blue),
              const SizedBox(height: 8),
              Text(title),
            ],
          ),
        ),
      ),
    );
  }
}
