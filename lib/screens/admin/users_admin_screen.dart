import 'package:flutter/material.dart';
import '../../services/supabase_data_service.dart';
import '../../models/user_model.dart';

class UsersAdminScreen extends StatefulWidget {
  const UsersAdminScreen({super.key});
  @override
  State<UsersAdminScreen> createState() => _UsersAdminScreenState();
}

class _UsersAdminScreenState extends State<UsersAdminScreen> {
  String _query = '';
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('إدارة المستخدمين')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              decoration: const InputDecoration(
                labelText: 'بحث بالاسم أو البريد أو الحساب',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: (v) => setState(() => _query = v.trim().toLowerCase()),
            ),
          ),
          Expanded(
            child: StreamBuilder<List<UserModel>>(
              stream: SupabaseDataService.instance.streamUsers(),
              builder: (context, snap) {
                if (snap.hasError) {
                  return const Center(child: Text('خطأ'));
                }
                if (!snap.hasData) {
                  return const Center(child: CircularProgressIndicator());
                }
                var users = snap.data!;
                if (_query.isNotEmpty) {
                  users = users.where((u) {
                    final s = '${u.fullName} ${u.email} ${u.accountNumber}'
                        .toLowerCase();
                    return s.contains(_query);
                  }).toList();
                }
                if (users.isEmpty) {
                  return const Center(child: Text('لا نتائج'));
                }
                return ListView.separated(
                  itemCount: users.length,
                  separatorBuilder: (_, _) => const Divider(),
                  itemBuilder: (context, i) {
                    final u = users[i];
                    return ListTile(
                      title: Text(u.fullName),
                      subtitle: Text('${u.email} • ${u.accountNumber}'),
                      trailing: Switch(
                        value: u.isActive,
                        onChanged: (v) {
                          SupabaseDataService.instance.setUserActive(
                            u.userId,
                            v,
                          );
                        },
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
