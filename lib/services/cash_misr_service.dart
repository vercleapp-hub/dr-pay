// خدمة للتعامل مع API كاش مصر
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:uuid/uuid.dart';
import '../models/cash_misr_models.dart';

class CashMisrService {
  static const String _baseUrl = 'https://api.e-misr.com';
  static const String _jsonEndpoint = '/json-v2.php';
  static const String _ajaxEndpoint = '/ajax.php';

  // بيانات الحساب (يجب تعديلها بالبيانات الفعلية من الكونفيج)
  static const String _aid = '996';
  static const String _pwd = 'C7L@-0z9VuX7S';
  static const String _bin = '9960';

  static final CashMisrService _instance = CashMisrService._internal();

  factory CashMisrService() {
    return _instance;
  }

  CashMisrService._internal();

  /// الاستعلام عن فاتورة أو رصيد
  /// يستخدم للحصول على تفاصيل المستحقات قبل الدفع
  Future<CashMisrInquiryResponse> inquire({
    required String serviceCode,
    required String reference,
    String? additionalKey1,
    String? additionalKey2,
    String? startDate,
    String? endDate,
    String? inquiryCode,
  }) async {
    try {
      final request = CashMisrInquiryRequest(
        aid: _aid,
        pwd: _pwd,
        bin: _bin,
        srv: serviceCode,
        cmob: reference,
        key1: additionalKey1,
        key2: additionalKey2,
        sdate: startDate,
        edate: endDate,
        incq: inquiryCode,
      );

      final response = await http
          .post(
            Uri.parse('$_baseUrl$_jsonEndpoint'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(request.toJson()),
          )
          .timeout(
            const Duration(seconds: 30),
            onTimeout: () => throw Exception('Timeout: Service not responding'),
          );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return CashMisrInquiryResponse.fromJson(data);
      } else {
        throw Exception('Failed to inquire: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Inquiry error: $e');
    }
  }

  /// دفع فاتورة أو رصيد
  /// يجب استدعاء inquire أولاً للحصول على cprid
  Future<CashMisrPaymentResponse> pay({
    required String serviceCode,
    required String reference,
    required double amount,
    required String? paymentReferenceId, // cprid من الاستعلام
    String? additionalKey1,
    String? additionalKey2,
  }) async {
    try {
      final transactionId = const Uuid().v1(); // Unique transaction ID

      final request = CashMisrPaymentRequest(
        aid: _aid,
        pwd: _pwd,
        bin: _bin,
        srv: serviceCode,
        cmob: reference,
        avsa: amount,
        apiid: transactionId,
        key1: additionalKey1,
        key2: additionalKey2,
        cprid: paymentReferenceId,
      );

      final response = await http
          .post(
            Uri.parse('$_baseUrl$_jsonEndpoint'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(request.toJson()),
          )
          .timeout(
            const Duration(seconds: 30),
            onTimeout: () => throw Exception('Timeout: Service not responding'),
          );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return CashMisrPaymentResponse.fromJson(data);
      } else {
        throw Exception('Failed to pay: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Payment error: $e');
    }
  }

  /// الاستعلام عن رصيد الحساب
  Future<double> getBalance() async {
    try {
      final payload = {
        'AID': _aid,
        'PWD': _pwd,
        'BIN': _bin,
        'srv': 'GetBalance',
      };

      final response = await http
          .post(
            Uri.parse('$_baseUrl$_ajaxEndpoint'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(payload),
          )
          .timeout(
            const Duration(seconds: 30),
            onTimeout: () => throw Exception('Timeout: Service not responding'),
          );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['ST'] == 'YES') {
          return double.tryParse(data['VSA']?.toString() ?? '0') ?? 0.0;
        }
        throw Exception(data['SMS'] ?? 'Failed to get balance');
      } else {
        throw Exception('Failed: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Balance error: $e');
    }
  }

  /// فحص فاتورة (للخدمات التي تحتاج فترة زمنية)
  Future<CashMisrInquiryResponse> checkInvoice({
    required String reference,
    required String startDate,
    required String endDate,
  }) async {
    try {
      final payload = {
        'AID': _aid,
        'PWD': _pwd,
        'BIN': _bin,
        'srv': 'chk',
        'cmob': reference,
        'sdate': startDate,
        'edate': endDate,
      };

      final response = await http
          .post(
            Uri.parse('$_baseUrl$_ajaxEndpoint'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(payload),
          )
          .timeout(
            const Duration(seconds: 30),
            onTimeout: () => throw Exception('Timeout: Service not responding'),
          );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return CashMisrInquiryResponse.fromJson(data);
      } else {
        throw Exception('Failed: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Check invoice error: $e');
    }
  }

  /// التحقق من حالة عملية سابقة
  Future<CashMisrPaymentResponse> checkStatus({
    required String transactionId,
  }) async {
    try {
      final payload = {
        'AID': _aid,
        'PWD': _pwd,
        'BIN': _bin,
        'srv': 'chkst',
        'APIID': transactionId,
        'do': 'QRY',
      };

      final response = await http
          .post(
            Uri.parse('$_baseUrl$_jsonEndpoint'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(payload),
          )
          .timeout(
            const Duration(seconds: 30),
            onTimeout: () => throw Exception('Timeout: Service not responding'),
          );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return CashMisrPaymentResponse.fromJson(data);
      } else {
        throw Exception('Failed: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Check status error: $e');
    }
  }

  /// قائمة بأكواد الخدمات المتاحة
  static const Map<String, String> serviceCategories = {
    // فواتير الكهرباء
    'elec': 'فواتير الكهرباء',

    // شحن الرصيد
    'movo': 'شحن فودافون',
    'moora': 'شحن أورانج',
    'moet': 'شحن اتصالات',
    'mowe': 'شحن وي WE',

    // كروت الشحن
    'vuvo': 'كروت فودافون',
    'vuora': 'كروت أورانج',
    'vuet': 'كروت اتصالات',
    'vuwe': 'كروت وي',

    // الإنترنت المنزلي
    'inte': 'إنترنت اتصالات',
    'inwe': 'إنترنت WE',
    'inora': 'إنترنت أورانج',
    'invod': 'إنترنت فودافون',

    // فواتير الموبيل
    'mobegypt': 'موبيل المصرية',

    // التعليم
    'tanta': 'جامعة طنطا',
    'benha': 'جامعة بنها',

    // الخدمات الأخرى
    'tmny': 'سحب نقدي',
    'chkst': 'التحقق من الحالة',
  };

  /// أكواد الخدمات مع تفاصيلها
  static const Map<String, Map<String, String>> serviceDetails = {
    'elec': {
      'name': 'فواتير الكهرباء',
      'description': 'دفع فواتير الكهرباء',
      'category': 'الخدمات الحكومية',
      'needsInquiry': 'true',
    },
    'movo': {
      'name': 'شحن فودافون',
      'description': 'شحن رصيد خط فودافون',
      'category': 'الاتصالات',
      'needsInquiry': 'false',
    },
    'elec_payment': {
      'name': 'دفع كهرباء',
      'description': 'دفع فاتورة الكهرباء',
      'category': 'الفواتير',
      'minAmount': '50',
      'maxAmount': '10000',
    },
  };
}
