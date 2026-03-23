// شاشة سجل العمليات (المستخدم يرى معاملاته)
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/firestore_service.dart';
import '../../providers/auth_provider.dart';

class TransactionsLogScreen extends StatelessWidget {
  const TransactionsLogScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final userId = auth.firebaseUser?.uid;

    if (userId == null) {
      return const Scaffold(body: Center(child: Text('يرجى تسجيل الدخول')));
    }

    return Scaffold(
      appBar: AppBar(title: const Text('سجل العمليات')),
      body: StreamBuilder<List<Map<String, dynamic>>>(
        stream: FirestoreService.instance.streamTransactionsForUserMap(userId),
        builder: (context, snap) {
          if (snap.hasError) {
            return const Center(child: Text('خطأ عند جلب السجل'));
          }
          if (!snap.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final items = snap.data!;
          if (items.isEmpty) return const Center(child: Text('لا توجد عمليات'));
          return ListView.separated(
            itemCount: items.length,
            separatorBuilder: (_, _) => const Divider(),
            itemBuilder: (context, i) {
              final it = items[i];
              final ts = it['timestamp'] is DateTime
                  ? it['timestamp'] as DateTime
                  : null;
              final date = ts != null ? ts.toString() : '';
              return ListTile(
                title: Text('${it['type'] ?? ''} • ${it['amount'] ?? ''}'),
                subtitle: Text('التفاصيل: ${it['note'] ?? ''}\n$date'),
                trailing: Text(it['status'] ?? ''),
                isThreeLine: true,
              );
            },
          );
        },
      ),
    );
  }
}
