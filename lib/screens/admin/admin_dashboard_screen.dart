import 'package:flutter/material.dart';

class AdminDashboardScreen extends StatelessWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('لوحة الإدارة')),
      body: GridView.count(
        crossAxisCount: MediaQuery.of(context).size.width > 600 ? 3 : 2,
        padding: const EdgeInsets.all(16),
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
        children: [
          _buildCard(
            context,
            icon: Icons.manage_accounts,
            title: 'إدارة المستخدمين',
            route: '/admin/users',
          ),
          _buildCard(
            context,
            icon: Icons.account_balance_wallet,
            title: 'طرق الإيداع',
            route: '/admin/deposit_methods',
          ),
          _buildCard(
            context,
            icon: Icons.miscellaneous_services,
            title: 'إدارة الخدمات',
            route: '/admin/services',
          ),
        ],
      ),
    );
  }

  Widget _buildCard(BuildContext context,
      {required IconData icon, required String title, required String route}) {
    return Card(
      elevation: 2,
      child: InkWell(
        onTap: () => Navigator.pushNamed(context, route),
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
