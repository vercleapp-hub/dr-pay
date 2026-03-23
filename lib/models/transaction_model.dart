import 'package:cloud_firestore/cloud_firestore.dart';

class TransactionModel {
  final String transactionId;
  final String senderId;
  final String receiverId;
  final double amount;
  final String type; // transfer, deposit, withdraw
  final String status; // pending, completed, failed
  final DateTime timestamp;
  final String? note;

  TransactionModel({
    required this.transactionId,
    required this.senderId,
    required this.receiverId,
    required this.amount,
    required this.type,
    required this.status,
    required this.timestamp,
    this.note,
  });

  factory TransactionModel.fromMap(Map<String, dynamic> map) {
    final ts = map['timestamp'];
    DateTime timestamp;
    if (ts is Timestamp) {
      timestamp = ts.toDate();
    } else if (ts is DateTime) {
      timestamp = ts;
    } else if (ts is String) {
      timestamp = DateTime.tryParse(ts) ?? DateTime.now();
    } else {
      timestamp = DateTime.now();
    }
    return TransactionModel(
      transactionId: map['transactionId'] as String,
      senderId: map['senderId'] as String,
      receiverId: map['receiverId'] as String,
      amount: (map['amount'] as num).toDouble(),
      type: map['type'] as String,
      status: map['status'] as String,
      timestamp: timestamp,
      note: map['note'] as String?,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'transactionId': transactionId,
      'senderId': senderId,
      'receiverId': receiverId,
      'amount': amount,
      'type': type,
      'status': status,
      'timestamp': timestamp,
      'note': note,
    };
  }
}
