// lib/screens/wallet/balance_screen.dart
// شاشة شحن الرصيد (محاكاة)

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import 'package:intl/intl.dart';
import 'package:flutter/services.dart';

import '../../services/supabase_data_service.dart';
import '../../providers/auth_provider.dart';

class BalanceScreen extends StatefulWidget {
  const BalanceScreen({super.key});

  @override
  State<BalanceScreen> createState() => _BalanceScreenState();
}

class _BalanceScreenState extends State<BalanceScreen> {
  final _amountController = TextEditingController();
  final _phoneController = TextEditingController();
  final _noteController = TextEditingController();
  bool _isLoading = false;
  String _selectedMethod = '';
  List<String> _methods = [];
  String _transactionNumber = '';
  File? _receiptImage;
  String _accountNumber = ''; // رقم الحساب البنكي

  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _generateTransactionNumber();
    _loadMethods();
    _loadUserAccountNumber();
  }

  void _generateTransactionNumber() {
    final now = DateTime.now();
    _transactionNumber = DateFormat('yyyyMMddHHmmss').format(now);
  }

  Future<void> _loadUserAccountNumber() async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final userId = auth.firebaseUser?.uid;
    if (userId != null) {
      final user = await SupabaseDataService.instance.getUser(userId);
      if (user != null && mounted) {
        setState(() => _accountNumber = user.accountNumber);
      }
    }
  }

  Future<void> _loadMethods() async {
    final methods = await SupabaseDataService.instance.getDepositMethods();
    setState(() {
      _methods = methods.isNotEmpty
          ? methods
          : ['Bank Transfer', 'Fawry', 'Vodafone Cash', 'Etisalat'];
      _selectedMethod = _methods.first;
    });
  }

  Future<void> _pickReceipt() async {
    final XFile? file = await _picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 70,
    );
    if (file != null) {
      setState(() => _receiptImage = File(file.path));
    }
  }

  Future<void> _submitDeposit() async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final userId = auth.firebaseUser?.uid ?? 'anonymous';

    final amountText = _amountController.text.trim();
    final phone = _phoneController.text.trim();
    if (amountText.isEmpty || double.tryParse(amountText) == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('أدخل مبلغ صالح')));
      return;
    }

    setState(() => _isLoading = true);

    try {
      final depositId = const Uuid().v4();
      // ملاحظة: رفع الصورة إلى تخزين لم يطبق هنا — نحفظ المسار المحلي كمرجع أولي.
      final deposit = {
        'depositId': depositId,
        'userId': userId,
        'method': _selectedMethod,
        'amount': double.parse(amountText),
        'transactionNumber': _transactionNumber,
        'senderPhone': phone,
        'receiptPath': _receiptImage?.path,
        'status': 'pending',
        'note': _noteController.text.trim(),
        'timestamp': DateTime.now(),
      };

      await SupabaseDataService.instance.createDeposit(deposit);

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم إرسال بيانات الإيداع بنجاح')),
      );
      // إعادة تهيئة الحقول لصفحة جديدة
      _amountController.clear();
      _phoneController.clear();
      _noteController.clear();
      setState(() {
        _receiptImage = null;
        _generateTransactionNumber();
      });
    } catch (e) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('خطأ: ${e.toString()}')));
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('شحن الرصيد')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'طريقة الإيداع',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            if (_methods.isEmpty)
              const SizedBox.shrink()
            else
              DropdownButtonFormField<String>(
                initialValue: _selectedMethod.isEmpty ? null : _selectedMethod,
                items: _methods
                    .map((m) => DropdownMenuItem(value: m, child: Text(m)))
                    .toList(),
                onChanged: (v) => setState(() => _selectedMethod = v ?? ''),
                decoration: const InputDecoration(border: OutlineInputBorder()),
              ),
            if (_selectedMethod.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 12.0),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.blue),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'رقم الحساب المستلم:',
                        style: TextStyle(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              _accountNumber.isEmpty
                                  ? 'جاري التحميل...'
                                  : _accountNumber,
                              style: const TextStyle(
                                fontSize: 16,
                                color: Colors.blue,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          IconButton(
                            tooltip: 'نسخ',
                            onPressed: _accountNumber.isEmpty
                                ? null
                                : () {
                                    Clipboard.setData(
                                      ClipboardData(text: _accountNumber),
                                    );
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                        content: Text('تم نسخ رقم الحساب'),
                                        duration: Duration(seconds: 1),
                                      ),
                                    );
                                  },
                            icon: const Icon(Icons.copy),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 12),

            TextField(
              controller: _amountController,
              keyboardType: TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'المبلغ بالجنيه',
                prefixText: 'ج.م ',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),

            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    initialValue: _transactionNumber,
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'رقم العملية / التحويل',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  tooltip: 'نسخ',
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: _transactionNumber));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('تم نسخ رقم العملية'),
                        duration: Duration(seconds: 1),
                      ),
                    );
                  },
                  icon: const Icon(Icons.copy),
                ),
              ],
            ),
            const SizedBox(height: 12),

            TextField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'رقم هاتف المرسل',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),

            const Text(
              'صورة إيصال التحويل',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _receiptImage == null
                      ? const Text('لم يتمّ اختيار أيّ ملفّ إرسال الإيداع')
                      : Image.file(
                          _receiptImage!,
                          height: 80,
                          fit: BoxFit.cover,
                        ),
                ),
                const SizedBox(width: 8),
                ElevatedButton.icon(
                  onPressed: _pickReceipt,
                  icon: const Icon(Icons.photo_library),
                  label: const Text('اختيار صورة'),
                ),
              ],
            ),
            const SizedBox(height: 12),

            TextField(
              controller: _noteController,
              decoration: const InputDecoration(
                labelText: 'ملاحظة (اختياري)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 20),

            if (_isLoading)
              const Center(child: CircularProgressIndicator())
            else
              ElevatedButton(
                onPressed: _submitDeposit,
                child: const Padding(
                  padding: EdgeInsets.symmetric(vertical: 14.0),
                  child: Text(
                    'إرسال طلب الإيداع',
                    style: TextStyle(fontSize: 16),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
