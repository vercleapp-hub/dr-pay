// شاشة طباعة إيصال حراري (عرض جاهز للطباعة)
import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:intl/intl.dart';

class ReceiptPrintScreen extends StatelessWidget {
  final Map<String, dynamic> deposit;
  const ReceiptPrintScreen({super.key, required this.deposit});

  @override
  Widget build(BuildContext context) {
    final ts = deposit['timestamp'];
    final date = ts is DateTime
        ? ts
        : (ts is Timestamp ? ts.toDate() : DateTime.now());
    final dateStr = DateFormat('yyyy-MM-dd HH:mm').format(date);

    final amount = deposit['amount'] ?? 0;
    final service = deposit['method'] ?? 'ELDoctor Pay';
    final tx = deposit['transactionNumber'] ?? deposit['depositId'] ?? '';

    return Scaffold(
      appBar: AppBar(title: const Text('إيصال دفع')),
      body: Center(
        child: Container(
          width: 320,
          padding: const EdgeInsets.all(12),
          color: Colors.white,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Image.asset(
                  'assets/logo.png',
                  height: 60,
                  fit: BoxFit.contain,
                  errorBuilder: (context, error, stackTrace) {
                    return const Text(
                      'ELDoctor Pay',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 8),
              const Divider(),
              Text('الخدمة: $service'),
              Text('التاريخ: $dateStr'),
              const SizedBox(height: 8),
              const Text('تفاصيل:'),
              const SizedBox(height: 4),
              const Text(
                '✔ عملية ناجحة',
                style: TextStyle(color: Colors.green),
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [Text('القيمة'), Text('$amount')],
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: const [Text('الرسوم'), Text('0')],
              ),
              const Divider(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [const Text('الإجمالي'), Text('$amount')],
              ),
              const SizedBox(height: 8),
              Text('مرجعي: $tx'),
              Text('رقم العملية: $tx'),
              const SizedBox(height: 8),
              const Text('عند البطء في الشبكة قد يستغرق التنفيذ حتى 24 ساعة'),
              const SizedBox(height: 4),
              const Text('تم الدفع عبر ELDoctor Pay'),
              const SizedBox(height: 8),
              const Text('الدعم: 01063151472'),
            ],
          ),
        ),
      ),
    );
  }
}
