// شاشة تقارير الإيداعات وسجل العمليات البسيط
import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../../services/firestore_service.dart';
import 'receipt_print_screen.dart';

class DepositsReportScreen extends StatelessWidget {
  const DepositsReportScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تقارير الإيداعات')),
      body: StreamBuilder<List<Map<String, dynamic>>>(
        stream: FirestoreService.instance.streamDeposits(limit: 200),
        builder: (context, snap) {
          if (snap.hasError) {
            return const Center(child: Text('خطأ بجلب التقارير'));
          }
          if (!snap.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final deposits = snap.data!;
          return SingleChildScrollView(
            padding: const EdgeInsets.all(8),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
              columns: const [
                DataColumn(label: Text('#')),
                DataColumn(label: Text('المبلغ')),
                DataColumn(label: Text('الطريقة')),
                DataColumn(label: Text('رقم التحويل')),
                DataColumn(label: Text('هاتف المرسل')),
                DataColumn(label: Text('الإيصال')),
                DataColumn(label: Text('الحالة')),
                DataColumn(label: Text('ملاحظة')),
                DataColumn(label: Text('التاريخ')),
                DataColumn(label: Text('طباعة')),
              ],
              rows: List.generate(deposits.length, (i) {
                final d = deposits[i];
                final ts = d['timestamp'] is DateTime
                    ? d['timestamp'] as DateTime
                    : (d['timestamp'] is Timestamp
                          ? (d['timestamp'] as Timestamp).toDate()
                          : null);
                final dateStr = ts != null ? ts.toString() : '';
                return DataRow(
                  cells: [
                    DataCell(Text('${i + 1}')),
                    DataCell(Text('${d['amount'] ?? ''}')),
                    DataCell(Text('${d['method'] ?? ''}')),
                    DataCell(Text('${d['transactionNumber'] ?? ''}')),
                    DataCell(Text('${d['senderPhone'] ?? ''}')),
                    DataCell(
                      Text(d['receiptPath'] != null ? 'موجود' : 'لا يوجد'),
                    ),
                    DataCell(Text('${d['status'] ?? ''}')),
                    DataCell(Text('${d['note'] ?? ''}')),
                    DataCell(Text(dateStr)),
                    DataCell(
                      IconButton(
                        icon: const Icon(Icons.print),
                        onPressed: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => ReceiptPrintScreen(deposit: d),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                );
              }),
            ),
            ),
          );
        },
      ),
    );
  }
}
