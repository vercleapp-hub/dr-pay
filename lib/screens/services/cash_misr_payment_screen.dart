/// شاشة لسداد الخدمات من كاش مصر
import 'package:flutter/material.dart';
import '../../services/cash_misr_service.dart' as cash_service;
import '../../models/cash_misr_models.dart';
import '../../services/firestore_service.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../models/transaction_model.dart';

class CashMisrPaymentScreen extends StatefulWidget {
  const CashMisrPaymentScreen({super.key});

  @override
  State<CashMisrPaymentScreen> createState() => _CashMisrPaymentScreenState();
}

class _CashMisrPaymentScreenState extends State<CashMisrPaymentScreen> {
  final _serviceController = TextEditingController();
  final _referenceController = TextEditingController();
  final _amountController = TextEditingController();
  final _key1Controller = TextEditingController();
  final _key2Controller = TextEditingController();

  String? _selectedService;
  CashMisrInquiryResponse? _inquiryResult;
  bool _isLoading = false;
  String? _errorMessage;
  int _currentStep = 0; // 0: inquire, 1: confirm, 2: payment

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('سداد الخدمات'),
        backgroundColor: Colors.blue,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // خطوات العملية
            _buildStepper(),
            const SizedBox(height: 24),

            if (_currentStep == 0) _buildInquiryStep(),
            if (_currentStep == 1) _buildConfirmStep(),
            if (_currentStep == 2) _buildPaymentStep(),

            if (_errorMessage != null)
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.red),
                  ),
                  child: Text(
                    _errorMessage!,
                    style: const TextStyle(color: Colors.red),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildStepper() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
      children: [
        _buildStepperItem('استعلام', 0),
        _buildStepperItem('تأكيد', 1),
        _buildStepperItem('دفع', 2),
      ],
    );
  }

  Widget _buildStepperItem(String label, int step) {
    final isActive = _currentStep >= step;
    return Column(
      children: [
        Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            color: isActive ? Colors.blue : Colors.grey.shade300,
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              '${step + 1}',
              style: TextStyle(
                color: isActive ? Colors.white : Colors.grey,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }

  Widget _buildInquiryStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // اختيار الخدمة
        DropdownButtonFormField<String>(
          initialValue: _selectedService,
          decoration: InputDecoration(
            labelText: 'نوع الخدمة',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            prefixIcon: const Icon(Icons.category),
          ),
          items: cash_service.CashMisrService.serviceCategories.entries.map((
            e,
          ) {
            return DropdownMenuItem(value: e.key, child: Text(e.value));
          }).toList(),
          onChanged: (value) {
            setState(() {
              _selectedService = value;
              _errorMessage = null;
            });
          },
        ),
        const SizedBox(height: 16),

        // رقم المرجع
        TextField(
          controller: _referenceController,
          decoration: InputDecoration(
            labelText: 'رقم المرجع (رقم العداد / رقم الحساب)',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            prefixIcon: const Icon(Icons.numbers),
            hintText: 'مثال: 01010211121',
          ),
          keyboardType: TextInputType.number,
        ),
        const SizedBox(height: 16),

        // مفاتيح إضافية (إن لزم)
        if (_selectedService == 'elec') ...[
          TextField(
            controller: _key1Controller,
            decoration: InputDecoration(
              labelText: 'كود الاستعلام',
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              hintText: '516',
            ),
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 16),
        ],

        if (_isLoading)
          const Center(child: CircularProgressIndicator())
        else
          ElevatedButton(
            onPressed: _handleInquiry,
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
              backgroundColor: Colors.blue,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: const Text(
              'استعلام',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ),
      ],
    );
  }

  Widget _buildConfirmStep() {
    if (_inquiryResult == null || !_inquiryResult!.isSuccess) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, color: Colors.red, size: 64),
            const SizedBox(height: 16),
            const Text('فشل الاستعلام'),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => setState(() => _currentStep = 0),
              child: const Text('العودة'),
            ),
          ],
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // تفاصيل الفاتورة
        Card(
          elevation: 2,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'تفاصيل الفاتورة',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                ..._inquiryResult!.info.map((info) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          info.name,
                          style: const TextStyle(
                            color: Colors.grey,
                            fontSize: 14,
                          ),
                        ),
                        Expanded(
                          child: Text(
                            info.value,
                            textAlign: TextAlign.end,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  );
                }),
                const Divider(),
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'المبلغ',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        '${_inquiryResult!.amount?.toStringAsFixed(2)} ج.م',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: Colors.blue,
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('الرسوم', style: TextStyle(fontSize: 14)),
                      Text(
                        '${_inquiryResult!.fee?.toStringAsFixed(2)} ج.م',
                        style: const TextStyle(fontSize: 14),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'الإجمالي',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        '${_inquiryResult!.totalAmount?.toStringAsFixed(2)} ج.م',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: Colors.green,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),

        // الأزرار
        Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed: () => setState(() => _currentStep = 0),
                child: const Text('تعديل'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton(
                onPressed: () => setState(() => _currentStep = 2),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.blue),
                child: const Text('تأكيد الدفع'),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildPaymentStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // تأكيد البيانات
        Card(
          elevation: 2,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'بيانات الدفع',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                _buildPaymentDetailRow(
                  'الخدمة',
                  cash_service
                          .CashMisrService
                          .serviceCategories[_selectedService] ??
                      '',
                ),
                _buildPaymentDetailRow('المرجع', _referenceController.text),
                _buildPaymentDetailRow(
                  'المبلغ',
                  '${_inquiryResult?.totalAmount?.toStringAsFixed(2)} ج.م',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),

        if (_isLoading)
          const Center(
            child: Column(
              children: [
                CircularProgressIndicator(),
                SizedBox(height: 16),
                Text('جاري معالجة الدفع...'),
              ],
            ),
          )
        else
          Column(
            children: [
              ElevatedButton(
                onPressed: _handlePayment,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: Colors.green,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Text(
                  'تأكيد الدفع',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => setState(() => _currentStep = 1),
                child: const Text('رجوع'),
              ),
            ],
          ),
      ],
    );
  }

  Widget _buildPaymentDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 14)),
          Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          ),
        ],
      ),
    );
  }

  Future<void> _handleInquiry() async {
    if (_selectedService == null || _referenceController.text.isEmpty) {
      setState(() {
        _errorMessage = 'الرجاء ملء جميع الحقول المطلوبة';
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await cash_service.CashMisrService().inquire(
        serviceCode: _selectedService!,
        reference: _referenceController.text,
        inquiryCode: _key1Controller.text.isNotEmpty
            ? _key1Controller.text
            : null,
      );

      if (response.isSuccess) {
        setState(() {
          _inquiryResult = response;
          _amountController.text = response.amount?.toString() ?? '';
          _currentStep = 1;
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = response.errorMessage ?? 'فشل الاستعلام';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'خطأ: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  Future<void> _handlePayment() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final auth = context.read<AuthProvider>();
      final userId = auth.firebaseUser?.uid;

      if (userId == null) {
        throw Exception('المستخدم غير مسجل');
      }

      final response = await cash_service.CashMisrService().pay(
        serviceCode: _selectedService!,
        reference: _referenceController.text,
        amount: _inquiryResult?.amount ?? 0,
        paymentReferenceId: _inquiryResult?.cprid,
      );

      if (response.isSuccess) {
        // حفظ العملية في Firestore
        final tx = TransactionModel(
          transactionId: DateTime.now().microsecondsSinceEpoch.toString(),
          senderId: userId,
          receiverId: 'cash_misr_service',
          amount: response.totalAmount ?? 0,
          type: 'service_payment',
          status: 'completed',
          timestamp: DateTime.now(),
          note:
              '${cash_service.CashMisrService.serviceCategories[_selectedService]} - ${_referenceController.text}',
        );
        await FirestoreService.instance.createTransaction(tx);

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                'تم الدفع بنجاح\nرقم العملية: ${response.systemTransactionId}',
              ),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 4),
            ),
          );
          Navigator.pop(context);
        }
      } else {
        setState(() {
          _errorMessage = response.errorMessage ?? 'فشل الدفع';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'خطأ: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  @override
  void dispose() {
    _serviceController.dispose();
    _referenceController.dispose();
    _amountController.dispose();
    _key1Controller.dispose();
    _key2Controller.dispose();
    super.dispose();
  }
}
