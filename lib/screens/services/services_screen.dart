// شاشة عرض الخدمات للمستخدم وإجراء الخدمة مع خصم الرصيد
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';

import '../../services/supabase_data_service.dart';
import '../../providers/auth_provider.dart';
import '../../models/transaction_model.dart';

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final userId = auth.firebaseUser?.uid;

    return Scaffold(
      appBar: AppBar(title: const Text('الخدمات')),
      body: StreamBuilder<List<Map<String, dynamic>>>(
        stream: SupabaseDataService.instance.streamServices(),
        builder: (context, snap) {
          if (snap.hasError) {
            return const Center(child: Text('خطأ بجلب الخدمات'));
          }
          if (!snap.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final services = snap.data!;
          if (services.isEmpty) {
            return const Center(child: Text('لا توجد خدمات'));
          }

          // Groupping by category
          final Map<String, List<Map<String, dynamic>>> byCategory = {};
          for (final s in services) {
            final cat = s['category']?.toString() ?? 'عام';
            byCategory.putIfAbsent(cat, () => []).add(s);
          }

          return ListView(
            children: byCategory.entries.map((e) {
              return ExpansionTile(
                title: Text(e.key),
                children: e.value.map((svc) {
                  return ListTile(
                    title: Text(svc['name'] ?? ''),
                    subtitle: Text('السعر: ${svc['price'] ?? ''}'),
                    trailing: ElevatedButton(
                      onPressed: userId == null
                          ? null
                          : () => _openService(context, svc, userId),
                      child: const Text('تنفيذ'),
                    ),
                  );
                }).toList(),
              );
            }).toList(),
          );
        },
      ),
    );
  }

  void _openService(
    BuildContext context,
    Map<String, dynamic> svc,
    String userId,
  ) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ServiceDetailScreen(service: svc, userId: userId),
      ),
    );
  }
}

class ServiceDetailScreen extends StatefulWidget {
  final Map<String, dynamic> service;
  final String userId;
  const ServiceDetailScreen({
    super.key,
    required this.service,
    required this.userId,
  });

  @override
  State<ServiceDetailScreen> createState() => _ServiceDetailScreenState();
}

class _ServiceDetailScreenState extends State<ServiceDetailScreen> {
  final Map<String, TextEditingController> _controllers = {};
  bool _isProcessing = false;

  @override
  void initState() {
    super.initState();
    final fields = List<Map<String, dynamic>>.from(
      widget.service['fields'] ?? [],
    );
    for (final f in fields) {
      _controllers[f['key'] ?? Uuid().v4()] = TextEditingController();
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _isProcessing = true);
    try {
      final wallet = await SupabaseDataService.instance.getWallet(widget.userId);
      if (!mounted) return;
      final price = (widget.service['price'] as num?)?.toDouble() ?? 0.0;
      if (wallet == null) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('محفظة غير موجودة')));
        return;
      }
      if (wallet.balance < price) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('الرصيد غير كافٍ')));
        return;
      }

      // خصم الرصيد وتسجيل المعاملة
      final newBalance = wallet.balance - price;
      await SupabaseDataService.instance
          .updateWalletBalanceByUser(widget.userId, newBalance);

      final tx = {
        'id': const Uuid().v4(),
        'senderId': widget.userId,
        'receiverId': widget.service['provider_id'],
        'amount': price,
        'type': 'service',
        'status': 'completed',
        'timestamp': DateTime.now(),
        'note': 'خدمة: ${widget.service['name'] ?? ''}',
      };
      await SupabaseDataService.instance.createTransaction(tx);

      // سجل طلب الخدمة
      final request = {
        'serviceId': widget.service['id'],
        'userId': widget.userId,
        'id': const Uuid().v4(),
        'fields': _controllers.map((k, v) => MapEntry(k, v.text)),
        'price': price,
        'status': 'completed',
        'timestamp': DateTime.now(),
      };
      await SupabaseDataService.instance.createServiceRequest(request);

      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('تم تنفيذ الخدمة بنجاح')));
      Navigator.of(context).pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('خطأ: ${e.toString()}')));
    } finally {
      setState(() => _isProcessing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final fields = List<Map<String, dynamic>>.from(
      widget.service['fields'] ?? [],
    );
    return Scaffold(
      appBar: AppBar(title: Text(widget.service['name'] ?? 'تفاصيل الخدمة')),
      body: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'السعر: ${widget.service['price'] ?? ''}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            ...fields.map((f) {
              final key = f['key'] ?? '';
              final ctrl = _controllers[key] ?? TextEditingController();
              return Padding(
                padding: const EdgeInsets.only(bottom: 8.0),
                child: TextField(
                  controller: ctrl,
                  decoration: InputDecoration(labelText: f['label'] ?? 'حقل'),
                ),
              );
            }),
            const SizedBox(height: 8),
            if (_isProcessing)
              const Center(child: CircularProgressIndicator())
            else
              ElevatedButton(
                onPressed: _submit,
                child: const Text('دفع وتنفيذ'),
              ),
          ],
        ),
      ),
    );
  }
}
