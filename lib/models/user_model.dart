import 'package:cloud_firestore/cloud_firestore.dart';

class UserModel {
  final String userId;
  final String email;
  final String fullName;
  final String phoneNumber;
  final String nationalId;
  final String address;
  final String accountNumber; // رقم حساب من 4 أرقام
  final String? idPhotoFront;
  final String? idPhotoBack;
  final double? latitude;
  final double? longitude;
  final DateTime createdAt;
  final bool isActive;
  final String role;

  UserModel({
    required this.userId,
    required this.email,
    required this.fullName,
    required this.phoneNumber,
    required this.nationalId,
    required this.address,
    required this.accountNumber,
    this.idPhotoFront,
    this.idPhotoBack,
    this.latitude,
    this.longitude,
    required this.createdAt,
    this.isActive = true,
    this.role = 'user',
  });

  factory UserModel.fromMap(Map<String, dynamic> map) {
    final ca = map['createdAt'];
    DateTime createdAt;
    if (ca is Timestamp) {
      createdAt = ca.toDate();
    } else if (ca is DateTime) {
      createdAt = ca;
    } else if (ca is String) {
      createdAt = DateTime.tryParse(ca) ?? DateTime.now();
    } else {
      createdAt = DateTime.now();
    }
    return UserModel(
      userId: map['userId'] as String,
      email: map['email'] as String,
      fullName: map['fullName'] as String,
      phoneNumber: map['phoneNumber'] as String,
      nationalId: map['nationalId'] as String? ?? '',
      address: map['address'] as String? ?? '',
      accountNumber: map['accountNumber'] as String? ?? '',
      idPhotoFront: map['idPhotoFront'] as String?,
      idPhotoBack: map['idPhotoBack'] as String?,
      latitude: (map['latitude'] as num?)?.toDouble(),
      longitude: (map['longitude'] as num?)?.toDouble(),
      createdAt: createdAt,
      isActive: map['isActive'] as bool? ?? true,
      role: map['role'] as String? ?? 'user',
    );
  }

  // تحويل النموذج إلى خريطة ليتم حفظها في Firestore
  Map<String, dynamic> toMap() {
    return {
      'userId': userId,
      'email': email,
      'fullName': fullName,
      'phoneNumber': phoneNumber,
      'nationalId': nationalId,
      'address': address,
      'accountNumber': accountNumber,
      'idPhotoFront': idPhotoFront,
      'idPhotoBack': idPhotoBack,
      'latitude': latitude,
      'longitude': longitude,
      'createdAt': createdAt,
      'isActive': isActive,
      'role': role,
    };
  }
}
