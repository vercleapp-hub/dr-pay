import 'package:cloud_firestore/cloud_firestore.dart';

class WalletModel {
  final String walletId;
  final String userId;
  final double balance;
  final String currency;
  final DateTime lastUpdated;

  WalletModel({
    required this.walletId,
    required this.userId,
    this.balance = 0.0,
    this.currency = 'EGP',
    required this.lastUpdated,
  });

  factory WalletModel.fromMap(Map<String, dynamic> map) {
    final lu = map['lastUpdated'];
    DateTime lastUpdated;
    if (lu is Timestamp) {
      lastUpdated = lu.toDate();
    } else if (lu is DateTime) {
      lastUpdated = lu;
    } else if (lu is String) {
      lastUpdated = DateTime.tryParse(lu) ?? DateTime.now();
    } else {
      lastUpdated = DateTime.now();
    }
    return WalletModel(
      walletId: map['walletId'] as String,
      userId: map['userId'] as String,
      balance: (map['balance'] as num).toDouble(),
      currency: map['currency'] as String,
      lastUpdated: lastUpdated,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'walletId': walletId,
      'userId': userId,
      'balance': balance,
      'currency': currency,
      'lastUpdated': lastUpdated,
    };
  }
}
