import 'package:flutter/material.dart';
import '../../services/supabase_data_service.dart';

class ServicesAdminScreen extends StatefulWidget {
  const ServicesAdminScreen({super.key});
  @override
  State<ServicesAdminScreen> createState() => _ServicesAdminScreenState();
}

class _ServicesAdminScreenState extends State<ServicesAdminScreen> {
  final _nameController = TextEditingController();
  final _categoryController = TextEditingController();
  final _priceController = TextEditingController(text: '0');
  final _fieldsController = TextEditingController(); // key:label, key2:label2
  bool _enabled = true;
  String? _editingId;

  @override
  void dispose() {
    _nameController.dispose();
    _categoryController.dispose();
    _priceController.dispose();
    _fieldsController.dispose();
    super.dispose();
  }

  void _startEdit(Map<String, dynamic> item) {
    setState(() {
      _editingId = (item['id'] ?? '').toString();
      _nameController.text = (item['name'] ?? '').toString();
      _categoryController.text = (item['category'] ?? '').toString();
      _priceController.text = (item['price'] ?? 0).toString();
      _enabled = item['enabled'] == true;
      final fields = item['fields'];
      if (fields is List) {
        _fieldsController.text = fields
            .map((e) => '${e['key']}:${e['label']}')
            .join(', ');
      } else if (fields is Map) {
        _fieldsController.text = (fields as Map)
            .entries
            .map((e) => '${e.key}:${e.value}')
            .join(', ');
      } else {
        _fieldsController.clear();
      }
    });
  }

  void _clearForm() {
    setState(() {
      _editingId = null;
      _nameController.clear();
      _categoryController.clear();
      _priceController.text = '0';
      _fieldsController.clear();
      _enabled = true;
    });
  }

  List<Map<String, String>> _parseFields(String input) {
    final parts = input.split(',').map((s) => s.trim()).where((s) => s.isNotEmpty);
    final list = <Map<String, String>>[];
    for (final p in parts) {
      final kv = p.split(':');
      if (kv.length >= 2) {
        list.add({'key': kv[0].trim(), 'label': kv[1].trim()});
      }
    }
    return list;
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    final category = _categoryController.text.trim();
    final price = double.tryParse(_priceController.text) ?? 0.0;
    if (name.isEmpty) return;
    final fields = _parseFields(_fieldsController.text);
    final data = {
      if (_editingId != null) 'id': _editingId,
      'name': name,
      'category': category,
      'price': price,
      'fields': fields,
      'enabled': _enabled,
    };
    await SupabaseDataService.instance.upsertService(data);
    _clearForm();
  }

  Future<void> _delete(String id) async {
    await SupabaseDataService.instance.deleteService(id);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('إدارة الخدمات')),
      body: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          children: [
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'اسم الخدمة',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _categoryController,
                    decoration: const InputDecoration(
                      labelText: 'الفئة',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: _priceController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'السعر',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  children: [
                    const Text('مفعّل'),
                    Switch(
                      value: _enabled,
                      onChanged: (v) => setState(() => _enabled = v),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _fieldsController,
              decoration: const InputDecoration(
                labelText: 'الحقول (key:label, key2:label2)',
                border: OutlineInputBorder(),
              ),
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
                stream: SupabaseDataService.instance.streamServices(),
                builder: (context, snap) {
                  if (snap.hasError) {
                    return const Center(child: Text('خطأ عند جلب الخدمات'));
                  }
                  if (!snap.hasData) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  final items = snap.data!;
                  if (items.isEmpty) {
                    return const Center(child: Text('لا توجد خدمات'));
                  }
                  return ListView.separated(
                    itemCount: items.length,
                    separatorBuilder: (_, __) => const Divider(),
                    itemBuilder: (context, i) {
                      final it = items[i];
                      return ListTile(
                        title: Text((it['name'] ?? '').toString()),
                        subtitle: Text(
                          'فئة: ${(it['category'] ?? '').toString()} • سعر: ${(it['price'] ?? 0).toString()} • ${it['enabled'] == true ? 'مفعل' : 'معطل'}',
                        ),
                        onTap: () => _startEdit(it),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete),
                          onPressed: () => _delete((it['id'] ?? '').toString()),
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
