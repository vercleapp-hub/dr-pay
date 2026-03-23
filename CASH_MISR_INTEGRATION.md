# دليل خدمات كاش مصر (Cash Misr Services)

## نظرة عامة

تم دمج خدمات كاش مصر الإلكترونية مع التطبيق لتمكين المستخدمين من دفع الفواتير والخدمات المختلفة.

## الملفات المضافة

### 1. Models (`lib/models/cash_misr_models.dart`)

#### CashMisrInquiryRequest
- طلب الاستعلام عن فاتورة أو رصيد
- Fields:
  - `aid`: معرف الحساب
  - `srv`: رمز الخدمة
  - `cmob`: رقم المرجع (رقم العداد، رقم الهاتف، إلخ)

#### CashMisrInquiryResponse
- رد الاستعلام مع تفاصيل الفاتورة
- Fields:
  - `status`: INF (معلومات), YES (نجح), ERR (خطأ)
  - `amount`: المبلغ المستحق
  - `fee`: الرسوم
  - `totalAmount`: الإجمالي
  - `cprid`: رقم المرجع للدفع (مطلوب للدفع)
  - `info`: معلومات إضافية

#### CashMisrPaymentRequest/Response
- طلب وردود الدفع

#### CashMisrTransaction
- سجل المعاملات المحفوظة في Firestore

### 2. Service (`lib/services/cash_misr_service.dart`)

الفئة الرئيسية للتعامل مع API كاش مصر

#### الدوال الرئيسية:

```dart
// الاستعلام عن فاتورة
Future<CashMisrInquiryResponse> inquire({
  required String serviceCode,      // مثال: 'elec' للكهرباء
  required String reference,        // رقم العداد أو الحساب
  String? additionalKey1,          // مفاتيح إضافية حسب الخدمة
  String? additionalKey2,
  String? startDate,               // للفترات الزمنية
  String? endDate,
  String? inquiryCode,             // لفواتير معينة
})

// الدفع
Future<CashMisrPaymentResponse> pay({
  required String serviceCode,
  required String reference,
  required double amount,
  required String? paymentReferenceId,  // cprid من الاستعلام
  String? additionalKey1,
  String? additionalKey2,
})

// الاستعلام عن الرصيد
Future<double> getBalance()

// التحقق من حالة عملية سابقة
Future<CashMisrPaymentResponse> checkStatus({
  required String transactionId,
})
```

#### أكواد الخدمات:

| Code | Service | الخدمة |
|------|---------|--------|
| elec | Electricity Bill | فاتورة الكهرباء |
| movo | Vodafone Airtime | شحن فودافون |
| moora | Orange Airtime | شحن أورانج |
| moet | Etisalat Airtime | شحن اتصالات |
| mowe | WE Airtime | شحن وي |
| inte | Etisalat Internet | إنترنت اتصالات |
| inwe | WE Internet | إنترنت وي |
| inora | Orange Internet | إنترنت أورانج |
| tmny | Cash Withdrawal | سحب نقدي |

### 3. Screen (`lib/screens/services/cash_misr_payment_screen.dart`)

واجهة المستخدم لسداد الخدمات

#### خطوات العملية (3 خطوات):

1. **خطوة الاستعلام**: اختيار الخدمة وإدخال رقم المرجع
2. **خطوة التأكيد**: عرض تفاصيل الفاتورة والرسوم
3. **خطوة الدفع**: تأكيد الدفع

## مثال الاستخدام

### 1. الاستعلام عن فاتورة كهرباء:

```dart
final response = await CashMisrService().inquire(
  serviceCode: 'elec',
  reference: '01010211121',
  inquiryCode: '516',
);

if (response.isSuccess) {
  print('المبلغ: ${response.amount}');
  print('الرسوم: ${response.fee}');
  print('الإجمالي: ${response.totalAmount}');
}
```

### 2. دفع الفاتورة:

```dart
final paymentResponse = await CashMisrService().pay(
  serviceCode: 'elec',
  reference: '01010211121',
  amount: response.amount ?? 0,
  paymentReferenceId: response.cprid,
);

if (paymentResponse.isSuccess) {
  print('رقم الطلب: ${paymentResponse.systemTransactionId}');
  print('رقم البنك: ${paymentResponse.bankTransactionId}');
}
```

### 3. شحن رصيد:

```dart
final response = await CashMisrService().inquire(
  serviceCode: 'movo',
  reference: '01010211122',
);

// لشحن الرصيد لا يحتاج استعلام، يمكن الدفع مباشرة
final paymentResponse = await CashMisrService().pay(
  serviceCode: 'movo',
  reference: '01010211122',
  amount: 25, // 25 جنيه مثلا
  paymentReferenceId: null,
);
```

## بيانات الحساب (مهم!)

البيانات الحالية في `cash_misr_service.dart`:

```dart
static const String _aid = '996';           // معرف الحساب
static const String _pwd = 'C7L@-0z9VuX7S'; // كلمة المرور
static const String _bin = '9960';          // Business ID
```

**⚠️ يجب تحديث هذه البيانات ببيانات الحساب الفعلية الخاص بك!**

## رموز الأخطاء

| Code | Message | الرسالة |
|------|---------|--------|
| -1 | MISSING_AUTH_DATA | بيانات المصادقة ناقصة |
| -2 | DATABASE_CONNECTION_ERROR | خطأ في الاتصال بقاعدة البيانات |
| -3 | INVALID_AUTH_DATA | بيانات المصادقة غير صحيحة |
| -4 | SERVICE_NOT_ALLOWED | الخدمة غير مسموحة |
| -101 | Failed Inquiry | فشل الاستعلام |
| -201 | Failed Payment | فشل الدفع |

## التدفق في التطبيق

### إضافة زر في الخدمات:

```dart
// في services_screen.dart
_buildServiceCard(
  Icons.payments,
  'سداد الخدمات',
  'فواتير وشحن الرصيد',
  '/cash_misr_payment',
)
```

### أو إنشاء الشاشة بديرة من أي مكان:

```dart
Navigator.pushNamed(context, '/cash_misr_payment');
```

## حفظ المعاملات

عند نجاح الدفع، يتم حفظ العملية تلقائياً:

```dart
await FirestoreService.instance.createTransaction(
  senderId: userId,
  receiverId: 'cash_misr_service',
  amount: response.totalAmount ?? 0,
  type: 'service_payment',
  status: 'completed',
  note: '${CashMisrService.serviceCategories[_selectedService]} - ${_referenceController.text}',
);
```

## الخدمات المتاحة الحالية

### فواتير الكهرباء
- Requires: رقم العداد، كود الاستعلام (516 عادة)
- API returns: تفاصيل الفاتورة، المستحقات

### شحن الرصيد (BenefitTransfer)
- Services: فودافون، أورانج، اتصالات، وي
- Requires: رقم الهاتف المراد الشحن
- Amount: قابل للتخصيص

### إنترنت منزلي
- Services: اتصالات، وي، أورانج، فودافون
- Requires: رقم الحساب

### سحب نقدي (tm'ny)
- للمحلات المسجلة فقط
- يتطلب معالجة خاصة

## ملاحظات مهمة

1. **التوقيت**: كل طلب استعلام أو دفع قد يستغرق 30 ثانية (الحد الأقصى المعرّف)

2. **الإعادة**: يمكن إعادة محاولة العملية في نفس اليوم باستخدام نفس APIID

3. **المصادقة**: كل طلب يجب أن يتضمن:
   - `AID`: معرف الحساب
   - `PWD`: كلمة المرور
   - `BIN`: رقم التعريف التجاري

4. **الأمان**: تأكد من:
   - عدم حفظ بيانات المصادقة في Git
   - استخدام متغيرات البيئة للبيانات الحساسة
   - تشفير البيانات المرسلة عبر HTTPS (بالفعل آمنة)

## التطوير المستقبلي

- [ ] ربط بـ API lists أكثر خدمات
- [ ] تخزين مؤقت للخدمات المتاحة
- [ ] إشعارات push عند اكتمال الدفع
- [ ] تقارير مفصلة للمعاملات
- [ ] رابط الفواتير والإيصالات
- [ ] دعم الدفع المتعدد

## الدعم والمساعدة

للمزيد من المعلومات عن كاش مصر API:
- الموقع: https://e-misr.com
- البريد: support@e-misr.com
- Postman Collection متوفرة في المشروع
