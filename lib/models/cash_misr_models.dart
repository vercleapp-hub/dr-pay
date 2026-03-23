// نماذج بيانات لخدمات كاش مصر

/// نموذج طلب الاستعلام
class CashMisrInquiryRequest {
  final String aid; // Account ID
  final String pwd; // Password
  final String bin; // Business Identification Number
  final String srv; // Service code (elec, movo, etc)
  final String cmob; // Customer mobile/reference
  final String? key1; // Optional key for some services
  final String? key2; // Optional key for some services
  final String? sdate; // Start date for some services
  final String? edate; // End date for some services
  final String? incq; // Inquiry code for electricity

  CashMisrInquiryRequest({
    required this.aid,
    required this.pwd,
    required this.bin,
    required this.srv,
    required this.cmob,
    this.key1,
    this.key2,
    this.sdate,
    this.edate,
    this.incq,
  });

  Map<String, dynamic> toJson() {
    return {
      'AID': aid,
      'PWD': pwd,
      'BIN': bin,
      'srv': srv,
      'cmob': cmob,
      if (key1 != null) 'Key1': key1,
      if (key2 != null) 'Key2': key2,
      if (sdate != null) 'sdate': sdate,
      if (edate != null) 'edate': edate,
      if (incq != null) 'incq': incq,
    };
  }
}

/// نموذج طلب الدفع
class CashMisrPaymentRequest {
  final String aid;
  final String pwd;
  final String bin;
  final String srv;
  final String cmob;
  final double avsa; // Amount
  final String apiid; // Unique transaction ID
  final String? key1;
  final String? key2;
  final String? cprid; // Payment reference ID from inquiry
  final String do_ = 'PAY'; // Action

  CashMisrPaymentRequest({
    required this.aid,
    required this.pwd,
    required this.bin,
    required this.srv,
    required this.cmob,
    required this.avsa,
    required this.apiid,
    this.key1,
    this.key2,
    this.cprid,
  });

  Map<String, dynamic> toJson() {
    return {
      'AID': aid,
      'PWD': pwd,
      'BIN': bin,
      'srv': srv,
      'cmob': cmob,
      'avsa': avsa,
      'APIID': apiid,
      'do': do_,
      if (key1 != null) 'Key1': key1,
      if (key2 != null) 'Key2': key2,
      if (cprid != null) 'CPRID': cprid,
    };
  }
}

/// نموذج استجابة الاستعلام
class CashMisrInquiryResponse {
  final String status; // INF = info, YES = success, ERR = error
  final String? errorNumber; // Error code if error
  final String? errorMessage; // Error message if error
  final double? amount; // Required amount
  final double? fee; // Service fee
  final double? totalAmount; // Total with fee
  final double? remainingBalance; // Remaining balance after payment
  final String? cprid; // Payment reference ID for payment request
  final List<InquiryInfo> info; // Additional info fields

  CashMisrInquiryResponse({
    required this.status,
    this.errorNumber,
    this.errorMessage,
    this.amount,
    this.fee,
    this.totalAmount,
    this.remainingBalance,
    this.cprid,
    required this.info,
  });

  factory CashMisrInquiryResponse.fromJson(Map<String, dynamic> json) {
    return CashMisrInquiryResponse(
      status: json['ST'] ?? 'ERR',
      errorNumber: json['NUM'],
      errorMessage: json['SMS'],
      amount: double.tryParse(json['VSA']?.toString() ?? '0'),
      fee: double.tryParse(json['FEE']?.toString() ?? '0'),
      totalAmount: double.tryParse(json['CUT']?.toString() ?? '0'),
      remainingBalance: double.tryParse(json['rVSA']?.toString() ?? '0'),
      cprid: json['CPRID'],
      info: _parseInfo(json['info'] ?? []),
    );
  }

  bool get isSuccess => status == 'INF' || status == 'YES';

  static List<InquiryInfo> _parseInfo(List<dynamic> infoList) {
    return infoList.map((item) {
      final map = item is Map ? item : <String, dynamic>{};
      return InquiryInfo(
        name: map['name']?.toString() ?? '',
        value: map['value']?.toString() ?? '',
      );
    }).toList();
  }
}

/// معلومة واحدة من الاستعلام
class InquiryInfo {
  final String name;
  final String value;

  InquiryInfo({required this.name, required this.value});
}

/// نموذج استجابة الدفع
class CashMisrPaymentResponse {
  final String status; // YES = success, ERR = error
  final String? errorNumber;
  final String? errorMessage;
  final double? amount;
  final double? fee;
  final double? totalAmount;
  final String? bankTransactionId; // BID - Bank transaction ID
  final String? systemTransactionId; // PID - System transaction ID
  final double? remainingBalance;
  final List<InquiryInfo> info;

  CashMisrPaymentResponse({
    required this.status,
    this.errorNumber,
    this.errorMessage,
    this.amount,
    this.fee,
    this.totalAmount,
    this.bankTransactionId,
    this.systemTransactionId,
    this.remainingBalance,
    required this.info,
  });

  factory CashMisrPaymentResponse.fromJson(Map<String, dynamic> json) {
    return CashMisrPaymentResponse(
      status: json['ST'] ?? 'ERR',
      errorNumber: json['NUM'],
      errorMessage: json['SMS'],
      amount: double.tryParse(json['VSA']?.toString() ?? '0'),
      fee: double.tryParse(json['FEE']?.toString() ?? '0'),
      totalAmount: double.tryParse(json['CUT']?.toString() ?? '0'),
      bankTransactionId: json['BID']?.toString(),
      systemTransactionId: json['PID']?.toString(),
      remainingBalance: double.tryParse(json['rVSA']?.toString() ?? '0'),
      info: _parseInfo(json['info'] ?? []),
    );
  }

  bool get isSuccess => status == 'YES';

  static List<InquiryInfo> _parseInfo(List<dynamic> infoList) {
    return infoList.map((item) {
      final map = item is Map ? item : <String, dynamic>{};
      return InquiryInfo(
        name: map['name']?.toString() ?? '',
        value: map['value']?.toString() ?? '',
      );
    }).toList();
  }
}

/// نموذج خدمة من كاش مصر
class CashMisrService {
  final String id;
  final String code; // Service code (elec, movo, etc)
  final String name;
  final String category;
  final String description;
  final List<ServiceField> fields; // Required fields for inquiry
  final double minAmount;
  final double maxAmount;
  final bool isActive;

  CashMisrService({
    required this.id,
    required this.code,
    required this.name,
    required this.category,
    required this.description,
    required this.fields,
    required this.minAmount,
    required this.maxAmount,
    required this.isActive,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'code': code,
      'name': name,
      'category': category,
      'description': description,
      'fields': fields.map((f) => f.toMap()).toList(),
      'minAmount': minAmount,
      'maxAmount': maxAmount,
      'isActive': isActive,
    };
  }

  factory CashMisrService.fromMap(Map<String, dynamic> map) {
    return CashMisrService(
      id: map['id'] ?? '',
      code: map['code'] ?? '',
      name: map['name'] ?? '',
      category: map['category'] ?? '',
      description: map['description'] ?? '',
      fields:
          (map['fields'] as List?)
              ?.map((f) => ServiceField.fromMap(f))
              .toList() ??
          [],
      minAmount: (map['minAmount'] as num?)?.toDouble() ?? 0.0,
      maxAmount: (map['maxAmount'] as num?)?.toDouble() ?? 999999.0,
      isActive: map['isActive'] ?? true,
    );
  }
}

/// حقل في الخدمة
class ServiceField {
  final String key;
  final String label;
  final String type; // text, number, date, etc
  final bool required;
  final String? hint;
  final String? pattern; // Regex pattern for validation
  final Map<String, dynamic>? metadata;

  ServiceField({
    required this.key,
    required this.label,
    required this.type,
    required this.required,
    this.hint,
    this.pattern,
    this.metadata,
  });

  Map<String, dynamic> toMap() {
    return {
      'key': key,
      'label': label,
      'type': type,
      'required': required,
      'hint': hint,
      'pattern': pattern,
      'metadata': metadata,
    };
  }

  factory ServiceField.fromMap(Map<String, dynamic> map) {
    return ServiceField(
      key: map['key'] ?? '',
      label: map['label'] ?? '',
      type: map['type'] ?? 'text',
      required: map['required'] ?? false,
      hint: map['hint'],
      pattern: map['pattern'],
      metadata: map['metadata'],
    );
  }
}

/// سجل معاملة كاش مصر
class CashMisrTransaction {
  final String id;
  final String userId;
  final String serviceCode;
  final String serviceName;
  final String refNumber; // Reference number from user input
  final double amount;
  final double fee;
  final double totalAmount;
  final String status; // pending, completed, failed
  final String? bankTransactionId;
  final String? systemTransactionId;
  final DateTime timestamp;
  final String? errorMessage;
  final Map<String, dynamic>? additionalInfo;

  CashMisrTransaction({
    required this.id,
    required this.userId,
    required this.serviceCode,
    required this.serviceName,
    required this.refNumber,
    required this.amount,
    required this.fee,
    required this.totalAmount,
    required this.status,
    this.bankTransactionId,
    this.systemTransactionId,
    required this.timestamp,
    this.errorMessage,
    this.additionalInfo,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'userId': userId,
      'serviceCode': serviceCode,
      'serviceName': serviceName,
      'refNumber': refNumber,
      'amount': amount,
      'fee': fee,
      'totalAmount': totalAmount,
      'status': status,
      'bankTransactionId': bankTransactionId,
      'systemTransactionId': systemTransactionId,
      'timestamp': timestamp.toIso8601String(),
      'errorMessage': errorMessage,
      'additionalInfo': additionalInfo,
    };
  }

  factory CashMisrTransaction.fromMap(Map<String, dynamic> map) {
    final ts = map['timestamp'];
    DateTime timestamp;
    if (ts is DateTime) {
      timestamp = ts;
    } else if (ts is String) {
      timestamp = DateTime.tryParse(ts) ?? DateTime.now();
    } else if (ts is int) {
      // treat as epoch milliseconds
      timestamp = DateTime.fromMillisecondsSinceEpoch(ts);
    } else {
      timestamp = DateTime.now();
    }

    return CashMisrTransaction(
      id: map['id'] ?? '',
      userId: map['userId'] ?? '',
      serviceCode: map['serviceCode'] ?? '',
      serviceName: map['serviceName'] ?? '',
      refNumber: map['refNumber'] ?? '',
      amount: (map['amount'] as num?)?.toDouble() ?? 0.0,
      fee: (map['fee'] as num?)?.toDouble() ?? 0.0,
      totalAmount: (map['totalAmount'] as num?)?.toDouble() ?? 0.0,
      status: map['status'] ?? 'pending',
      bankTransactionId: map['bankTransactionId'],
      systemTransactionId: map['systemTransactionId'],
      timestamp: timestamp,
      errorMessage: map['errorMessage'],
      additionalInfo: map['additionalInfo'],
    );
  }
}
