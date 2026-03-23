# تحديثات نظام تطبيق الدفع
## System Enhancement Summary

---

## 📦 **الحزم المضافة الجديدة**
### New Dependencies Added

```yaml
image_picker: ^1.0.7          # رفع صور من الجهاز
geolocator: ^11.1.0           # الحصول على الموقع الجغرافي
shared_preferences: ^2.2.2    # حفظ البيانات محلياً
```

---

## 🔄 **التحديثات الرئيسية**
### Key Enhancements

### 1. **نموذج المستخدم المحسّن** 
**File:** `lib/models/user_model.dart`

الحقول الجديدة المضافة:
- `nationalId` - الرقم القومي/الوطني
- `address` - العنوان بالتفصيل
- `idPhotoFront` - صورة البطاقة (الوجه) - Base64
- `idPhotoBack` - صورة البطاقة (الظهر) - Base64
- `latitude` - خط العرض الجغرافي
- `longitude` - خط الطول الجغرافي

```dart
UserModel(
  userId: '...',
  email: 'user@example.com',
  fullName: 'أحمد محمد علي',
  phoneNumber: '01012345678',
  nationalId: '12345678901234',      // جديد
  address: 'شارع التحرير، القاهرة',    // جديد
  idPhotoFront: 'base64_image...',    // جديد
  idPhotoBack: 'base64_image...',     // جديد
  latitude: 30.0444,                 // جديد
  longitude: 31.2357,                // جديد
  createdAt: DateTime.now(),
)
```

---

### 2. **خدمة الموقع الجغرافي الجديدة**
**File:** `lib/services/location_service.dart`

```dart
LocationService.instance.getCurrentLocation()  // جلب الموقع الحالي مع الصلاحيات
LocationService.instance.requestLocationPermission()  // طلب صلاحية الموقع
LocationService.instance.isLocationServiceEnabled()  // فحص تفعيل خدمات الموقع
```

**الصلاحيات المطلوبة (في AndroidManifest.xml):**
- `android.permission.ACCESS_FINE_LOCATION` - الموقع الدقيق
- `android.permission.ACCESS_COARSE_LOCATION` - الموقع التقريبي

---

### 3. **خدمة التخزين المحلي الجديدة**
**File:** `lib/services/storage_service.dart`

```dart
StorageService.instance.saveLoginCredentials(email, password)  // حفظ بيانات الدخول
StorageService.instance.getSavedLoginCredentials()  // استرجاع البيانات المحفوظة
StorageService.instance.isRemembered()  // التحقق من Remember Me
StorageService.instance.clearSavedCredentials()  // حذف البيانات المحفوظة
```

---

### 4. **صفحة التسجيل المحسّنة**
**File:** `lib/screens/auth/register_screen.dart`

#### الحقول المضافة:
✅ **الاسم الكامل** (موجود)
✅ **الرقم القومي** (جديد)
✅ **رقم المحمول** (جديد)
✅ **العنوان بالتفصيل** (جديد)
✅ **البريد الإلكتروني** (موجود)
✅ **كلمة المرور** (موجود)
✅ **تأكيد كلمة المرور** (موجود)
✅ **صورة البطاقة (الوجه)** (جديد)
✅ **صورة البطاقة (الظهر)** (جديد)
✅ **الموقع الجغرافي** (جديد)

#### الميزات:
```dart
// اختيار صورة بصيغة JPG حتى 5MB
_pickImage(bool isFront) // رفع صورة البطاقة

// جلب الموقع الجغرافي مع الصلاحيات
_getLocation()

// تحقق شامل من جميع الحقول قبل الحفظ
_register() // حفظ جميع البيانات في Firestore مع الصور
```

---

### 5. **صفحة تسجيل الدخول المحسّنة**
**File:** `lib/screens/auth/login_screen.dart`

#### ميزة Remember Me (حفظ البيانات):
```dart
_rememberMe  // Checkbox لتفعيل/إلغاء حفظ البيانات

_loadSavedCredentials()  // استرجاع البيانات المحفوظة عند فتح الصفحة

_signIn()  // حفظ البيانات تلقائياً إذا تم تفعيل Remember Me
```

#### السلوك:
- إذا تم تفعيل `Remember Me` → يتم حفظ البريد وكلمة المرور محلياً
- عند فتح الصفحة → يتم استدعاء البيانات المحفوظة تلقائياً
- إذا تم إلغاء `Remember Me` → يتم حذف البيانات المحفوظة

---

## 🔐 **صلاحيات Android المضافة**
**File:** `android/app/src/main/AndroidManifest.xml`

```xml
<!-- الموقع الجغرافي (مطلوب) -->
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />

<!-- الوصول للصور (مطلوب لرفع صور البطاقة) -->
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
```

---

## 📊 **كيفية استخدام النظام الجديد**

### سيناريو: مستخدم جديد يريد إنشاء حساب

```
1. المستخدم ينقر على "إنشاء حساب جديد"
   ↓
2. يملأ جميع الحقول:
   - الاسم الكامل: "أحمد محمد علي"
   - الرقم القومي: "12345678901234"
   - رقم المحمول: "01012345678"
   - العنوان: "شارع النيل، الجيزة، مصر"
   - البريد الإلكتروني: "ahmed@example.com"
   - كلمة المرور: "MySecurePass123"
   ↓
3. ينقر على "جلب الموقع الجغرافي"
   ↓
4. يقبل الصلاحيات (Location Permission)
   ↓
5. يرفع صورة البطاقة (الوجه)
   ↓
6. يرفع صورة البطاقة (الظهر)
   ↓
7. ينقر على "إنشاء الحساب"
   ↓
8. يتم حفظ جميع البيانات في Firestore مع:
   - الصور (Base64 Format)
   - الموقع الجغرافي
   - بيانات المستخدم
   ↓
9. يتم إرسال رسالة تحقق بالبريد الإلكتروني
   ↓
10. يتم توجيهه إلى صفحة تسجيل الدخول
```

### سيناريو: تسجيل الدخول مع Remember Me

```
1. المستخدم يدخل البريد وكلمة المرور
   ↓
2. ينقر على Checkbox "تذكر بيانات الدخول"
   ↓
3. ينقر على "تسجيل الدخول"
   ↓
4. يتم حفظ البيانات في SharedPreferences
   ↓
5. في المرة القادمة:
   - يفتح التطبيق
   - البيانات تُحمل تلقائياً
   - يمكنه الضغط مباشرة على "تسجيل الدخول"
   - أو يلغي Remember Me لحذف البيانات
```

---

## 🗂️ **هيكل الملفات المحدث**

```
lib/
├── main.dart (Firebase init بقيم حقيقية ✓)
├── firebase_options.dart (مع بيانات Firebase الحقيقية)
├── models/
│   ├── user_model.dart ✨ [محدّث مع حقول جديدة]
│   ├── wallet_model.dart
│   └── transaction_model.dart
├── services/
│   ├── auth_service.dart
│   ├── firestore_service.dart
│   ├── supabase_service.dart
│   ├── location_service.dart ✨ [جديد]
│   └── storage_service.dart ✨ [جديد]
├── providers/
│   └── auth_provider.dart
├── screens/
│   ├── auth/
│   │   ├── login_screen.dart ✨ [محدّث مع Remember Me]
│   │   └── register_screen.dart ✨ [محدّث مع حقول جديدة]
│   ├── home/
│   │   └── home_screen.dart
│   ├── wallet/
│   ├── history/
│   └── profile/
│       └── profile_screen.dart ✨ [محدّث مع حقول جديدة]
└── widgets/
```

---

## ✅ **الحالة الحالية**

```
✓ Firebase Configuration - جاهز
✓ Google Services Plugin - مثبت
✓ Code Analysis - نظيف (No errors)
✓ Location Service - جاهز
✓ Image Picker - جاهز
✓ Shared Preferences - جاهز
✓ Android Permissions - مضافة
✓ User Model - محدّث
✓ Registration Screen - محسّن
✓ Login Screen - محسّن
```

---

## 🚀 **التالي: التشغيل**

### تشغيل على محاكي Android:
```bash
flutter devices    # عرض الأجهزة المتاحة
flutter run -d emulator-5554
```

### تشغيل على جهاز فعلي:
```bash
flutter run -d <device-id>
```

### البناء للإطلاق:
```bash
flutter build apk --release
flutter build apk --split-per-abi
```

---

## 📱 **بيانات Firebase المستخدمة**

- **Project ID:** `app-dr-pay`
- **Package Name:** `dr.pay`
- **API Key:** `AIzaSyA5ipQV7dcjj2l_UWjbwbIjCCxJ6s5iwv0`
- **Messaging Sender ID:** `932561146751`
- **Storage Bucket:** `app-dr-pay.firebasestorage.app`

---

**التاريخ:** 2026-03-03
**الحالة:** ✅ تم الانتهاء من جميع التحديثات
