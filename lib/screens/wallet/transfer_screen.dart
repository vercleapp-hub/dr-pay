// lib/screens/wallet/transfer_screen.dart
// شاشة التحويل، تبحث عن مستخدم وتقوم بنقل المبلغ

import 'package:flutter/material.dart';
import '../../services/firestore_service.dart';
import '../../models/transaction_model.dart';
import '../../providers/auth_provider.dart';
import 'package:provider/provider.dart';

class TransferScreen extends StatefulWidget {
  const TransferScreen({super.key});

  @override
  State<TransferScreen> createState() => _TransferScreenState();
}

class _TransferScreenState extends State<TransferScreen> {
  final _recipientController = TextEditingController();
  final _amountController = TextEditingController();
  final _noteController = TextEditingController();
  bool _isLoading = false;

  Future<void> _transfer() async {
    setState(() => _isLoading = true);
    final senderId = context.read<AuthProvider>().firebaseUser?.uid;
    final recipientAccountNumber = _recipientController.text.trim();
    final amount = double.tryParse(_amountController.text.trim()) ?? 0.0;

    // البحث عن المستخدم برقم الحساب
    if (senderId == null || amount <= 0) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('بيانات غير صحيحة')));
      }
    } else {
      final receiver = await FirestoreService.instance.getUserByAccountNumber(
        recipientAccountNumber,
      );
      if (receiver == null) {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('رقم الحساب غير موجود')));
        }
      } else {
        // التحقق من الرصيد
        final senderWallet = await FirestoreService.instance.getWallet(
          senderId,
        );
        if (senderWallet == null || senderWallet.balance < amount) {
          if (mounted) {
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(const SnackBar(content: Text('الرصيد غير كافٍ')));
          }
        } else {
          // خصم من المرسل وإضافة للمستقبل
          await FirestoreService.instance.updateWalletBalance(
            senderId,
            senderWallet.balance - amount,
          );
          final receiverWallet = await FirestoreService.instance.getWallet(
            receiver.userId,
          );
          if (receiverWallet != null) {
            await FirestoreService.instance.updateWalletBalance(
              receiver.userId,
              receiverWallet.balance + amount,
            );
          }

          final txModel = TransactionModel(
            transactionId: DateTime.now().millisecondsSinceEpoch.toString(),
            senderId: senderId,
            receiverId: receiver.userId,
            amount: amount,
            type: 'transfer',
            status: 'completed',
            timestamp: DateTime.now(),
            note: _noteController.text.trim(),
          );
          await FirestoreService.instance.createTransaction(txModel);
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('تم التحويل إلى ${receiver.fullName} بنجاح'),
              ),
            );
            _recipientController.clear();
            _amountController.clear();
            _noteController.clear();
          }
        }
      }
    }

    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تحويل الأموال')),
      body: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            TextField(
              controller: _recipientController,
              decoration: const InputDecoration(
                labelText: 'رقم حساب المستقبل',
                hintText: 'أدخل رقم الحساب (4 أرقام)',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.account_circle),
              ),
            ),
            const SizedBox(height: 15),
            TextField(
              controller: _amountController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'المبلغ',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 15),
            TextField(
              controller: _noteController,
              decoration: const InputDecoration(
                labelText: 'ملاحظة (اختياري)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 20),
            if (_isLoading)
              const CircularProgressIndicator()
            else
              ElevatedButton(
                onPressed: _transfer,
                child: const Text('تأكيد التحويل'),
              ),
          ],
        ),
      ),
    );
  }
}
