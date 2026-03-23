// شاشة إدارة طرق الإيداع (للأدمن)
import 'package:flutter/material.dart';

import '../../services/supabase_data_service.dart';

class DepositMethodsScreen extends StatefulWidget {
  const DepositMethodsScreen({super.key});

  @override
  State<DepositMethodsScreen> createState() => _DepositMethodsScreenState();
}

class _DepositMethodsScreenState extends State<DepositMethodsScreen> {
  final _nameController = TextEditingController();
  final _orderController = TextEditingController(text: '0');
  bool _enabled = true;
  String? _editingId;

  @override
  void dispose() {
    _nameController.dispose();
    _orderController.dispose();
    super.dispose();
  }

  void _startEdit(Map<String, dynamic> item) {
    setState(() {
      _editingId = item['id'] as String?;
      _nameController.text = (item['name'] ?? '').toString();
      _orderController.text = (item['order'] ?? 0).toString();
      _enabled = item['enabled'] ?? true;
    });
  }

  void _clearForm() {
    setState(() {
      _editingId = null;
      _nameController.clear();
      _orderController.text = '0';
      _enabled = true;
    });
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    final order = int.tryParse(_orderController.text) ?? 0;
    if (name.isEmpty) return;
    final data = {
      if (_editingId != null) 'id': _editingId,
      'name': name,
      'sort_order': order,
      'enabled': _enabled
    };
    await SupabaseDataService.instance.upsertDepositMethod(data);
    _clearForm();
  }

  Future<void> _delete(String id) async {
    await SupabaseDataService.instance.deleteDepositMethod(id);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('إدارة طرق الإيداع')),
      body: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          children: [
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'اسم الطريقة',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _orderController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'ترتيب',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  children: [
                    const Text('مفعل'),
                    Switch(
                      value: _enabled,
                      onChanged: (v) => setState(() => _enabled = v),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                ElevatedButton(onPressed: _save, child: const Text('حفظ')),
                const SizedBox(width: 8),
                TextButton(onPressed: _clearForm, child: const Text('إلغاء')),
              ],
            ),
            const SizedBox(height: 12),
            const Divider(),
            Expanded(
              child: StreamBuilder<List<Map<String, dynamic>>>(
                stream: SupabaseDataService.instance.streamDepositMethods(),
                builder: (context, snap) {
                  if (snap.hasError) {
                    return const Center(child: Text('خطأ عند جلب الطرق'));
                  }
                  if (!snap.hasData) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  final items = snap.data!;
                  if (items.isEmpty) {
                    return const Center(child: Text('لا توجد طرق'));
                  }
                  return ListView.separated(
                    itemCount: items.length,
                    separatorBuilder: (_, _) => const Divider(),
                    itemBuilder: (context, i) {
                      final it = items[i];
                      return ListTile(
                        title: Text((it['name'] ?? '').toString()),
                        subtitle: Text(
                          'ترتيب: ${(it['sort_order'] ?? 0).toString()}  •  ${it['enabled'] == true ? 'مفعل' : 'معطل'}',
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.edit),
                              onPressed: () => _startEdit(it),
                            ),
                            IconButton(
                              icon: const Icon(Icons.delete),
                              onPressed: () => _delete((it['id'] ?? '').toString()),
                            ),
                          ],
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
