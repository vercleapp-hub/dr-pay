// lib/screens/history/transactions_screen.dart
// قائمة بجميع المعاملات الخاصة بالمستخدم

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/firestore_service.dart';
import '../../models/transaction_model.dart';
import '../../widgets/transaction_card.dart';

class TransactionsScreen extends StatelessWidget {
  const TransactionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final userId = context.watch<AuthProvider>().firebaseUser?.uid ?? '';

    return Scaffold(
      appBar: AppBar(
        title: const Text('سجل المعاملات'),
      ),
      body: StreamBuilder<List<TransactionModel>>(
          stream: FirestoreService.instance.transactionsForUser(userId),
          builder: (context, sentSnap) {
            if (sentSnap.hasError) {
              return const Center(child: Text('حدث خطأ في تحميل المعاملات'));
            }
            return StreamBuilder<List<TransactionModel>>(
              stream: FirestoreService.instance
                  .transactionsReceivedForUser(userId),
              builder: (context, recvSnap) {
                if (!sentSnap.hasData || !recvSnap.hasData) {
                  return const Center(child: CircularProgressIndicator());
                }
                final list = <TransactionModel>[
                  ...sentSnap.data!,
                  ...recvSnap.data!
                ]..sort((a, b) => b.timestamp.compareTo(a.timestamp));
                if (list.isEmpty) {
                  return const Center(child: Text('لا توجد معاملات'));
                }
                return ListView.builder(
                  itemCount: list.length,
                  itemBuilder: (context, index) {
                    return TransactionCard(model: list[index]);
                  },
                );
              },
            );
          }),
    );
  }
}
