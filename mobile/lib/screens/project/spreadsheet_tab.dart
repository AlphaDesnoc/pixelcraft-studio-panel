import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/extras.dart';
import '../../services/auth_session.dart';

String columnLabel(int index) {
  var n = index;
  final buffer = StringBuffer();
  while (n >= 0) {
    buffer.writeCharCode(65 + (n % 26));
    n = n ~/ 26 - 1;
  }
  return buffer.toString().split('').reversed.join();
}

class SpreadsheetTab extends StatefulWidget {
  const SpreadsheetTab({
    super.key,
    required this.projectSlug,
    required this.sheets,
    required this.canWrite,
    required this.onChanged,
  });

  final String projectSlug;
  final List<WorkspaceSheet> sheets;
  final bool canWrite;
  final Future<void> Function() onChanged;

  @override
  State<SpreadsheetTab> createState() => _SpreadsheetTabState();
}

class _SpreadsheetTabState extends State<SpreadsheetTab> {
  int _activeIndex = 0;
  Timer? _saveTimer;
  Map<String, dynamic>? _pendingData;
  bool _saving = false;

  WorkspaceSheet get _sheet => widget.sheets[_activeIndex];

  @override
  void dispose() {
    _saveTimer?.cancel();
    super.dispose();
  }

  void _scheduleSave(Map<String, dynamic> data) {
    if (!widget.canWrite) return;
    _pendingData = data;
    _saveTimer?.cancel();
    _saveTimer = Timer(const Duration(milliseconds: 600), _flushSave);
  }

  Future<void> _flushSave() async {
    if (_pendingData == null || !mounted) return;
    setState(() => _saving = true);
    try {
      await context.read<AuthSession>().api.updateSheet(
            projectSlug: widget.projectSlug,
            sheetId: _sheet.id,
            data: _pendingData,
          );
      _pendingData = null;
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _renameSheet(WorkspaceSheet sheet) async {
    final controller = TextEditingController(text: sheet.name);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Renommer la feuille'),
        content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Nom')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enregistrer')),
        ],
      ),
    );
    if (ok != true || controller.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.updateSheet(
          projectSlug: widget.projectSlug,
          sheetId: sheet.id,
          name: controller.text.trim(),
        );
    await widget.onChanged();
  }

  Future<void> _deleteSheet(WorkspaceSheet sheet) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer la feuille ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteSheet(
          projectSlug: widget.projectSlug,
          sheetId: sheet.id,
        );
    setState(() => _activeIndex = 0);
    await widget.onChanged();
  }

  Future<void> _reorderSheet(int from, int to) async {
    if (from == to) return;
    final order = widget.sheets.map((s) => s.id).toList();
    final id = order.removeAt(from);
    order.insert(to, id);
    await context.read<AuthSession>().api.reorderSheets(
          projectSlug: widget.projectSlug,
          order: order,
        );
    setState(() => _activeIndex = to);
    await widget.onChanged();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.sheets.isEmpty) {
      return Center(
        child: widget.canWrite
            ? FilledButton(
                onPressed: () async {
                  await context.read<AuthSession>().api.createSheet(projectSlug: widget.projectSlug);
                  await widget.onChanged();
                },
                child: const Text('Créer une feuille'),
              )
            : const Text('Aucune feuille'),
      );
    }

    final rows = _sheet.rows.clamp(1, 100);
    final cols = _sheet.cols.clamp(1, 26);
    final data = _pendingData ?? _sheet.data;

    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (var i = 0; i < widget.sheets.length; i++)
                Padding(
                  padding: const EdgeInsets.only(left: 8, top: 8),
                  child: InputChip(
                    label: Text(widget.sheets[i].name),
                    selected: i == _activeIndex,
                    onSelected: (selected) {
                      if (selected) setState(() => _activeIndex = i);
                    },
                    onDeleted: widget.canWrite ? () => _deleteSheet(widget.sheets[i]) : null,
                    deleteIcon: widget.canWrite ? const Icon(Icons.close, size: 16) : null,
                    avatar: widget.canWrite
                        ? IconButton(
                            icon: const Icon(Icons.edit, size: 16),
                            onPressed: () => _renameSheet(widget.sheets[i]),
                          )
                        : null,
                  ),
                ),
              if (widget.canWrite) ...[
                IconButton(
                  onPressed: () async {
                    await context.read<AuthSession>().api.createSheet(projectSlug: widget.projectSlug);
                    await widget.onChanged();
                  },
                  icon: const Icon(Icons.add),
                ),
                if (_activeIndex > 0)
                  IconButton(
                    tooltip: 'Déplacer à gauche',
                    onPressed: () => _reorderSheet(_activeIndex, _activeIndex - 1),
                    icon: const Icon(Icons.arrow_back),
                  ),
                if (_activeIndex < widget.sheets.length - 1)
                  IconButton(
                    tooltip: 'Déplacer à droite',
                    onPressed: () => _reorderSheet(_activeIndex, _activeIndex + 1),
                    icon: const Icon(Icons.arrow_forward),
                  ),
              ],
            ],
          ),
        ),
        if (_saving) const LinearProgressIndicator(minHeight: 2),
        Expanded(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: SingleChildScrollView(
              child: Table(
                defaultColumnWidth: const FixedColumnWidth(96),
                border: TableBorder.all(color: Colors.white24),
                children: [
                  TableRow(
                    children: [
                      const SizedBox(width: 40, height: 32),
                      for (var c = 0; c < cols; c++)
                        Center(child: Text(columnLabel(c))),
                    ],
                  ),
                  for (var r = 0; r < rows; r++)
                    TableRow(
                      children: [
                        SizedBox(
                          height: 36,
                          child: Center(child: Text('${r + 1}')),
                        ),
                        for (var c = 0; c < cols; c++)
                          _CellField(
                            key: ValueKey('${_sheet.id}-$r-$c'),
                            initialValue: _cellValue(data, r, c),
                            readOnly: !widget.canWrite,
                            onChanged: (value) {
                              final key = '${columnLabel(c)}${r + 1}';
                              final next = WorkspaceSheet(
                                id: _sheet.id,
                                name: _sheet.name,
                                position: _sheet.position,
                                rows: _sheet.rows,
                                cols: _sheet.cols,
                                data: data,
                              ).withCellValue(key, value);
                              setState(() => _pendingData = next);
                              _scheduleSave(next);
                            },
                          ),
                      ],
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  String _cellValue(Map<String, dynamic> data, int row, int col) {
    final key = '${columnLabel(col)}${row + 1}';
    final cell = data[key];
    if (cell is Map && cell['v'] != null) return cell['v'].toString();
    return '';
  }
}

class _CellField extends StatefulWidget {
  const _CellField({
    super.key,
    required this.initialValue,
    required this.onChanged,
    required this.readOnly,
  });

  final String initialValue;
  final ValueChanged<String> onChanged;
  final bool readOnly;

  @override
  State<_CellField> createState() => _CellFieldState();
}

class _CellFieldState extends State<_CellField> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue);
  }

  @override
  void didUpdateWidget(covariant _CellField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialValue != widget.initialValue &&
        _controller.text != widget.initialValue) {
      _controller.text = widget.initialValue;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 36,
      child: TextField(
        controller: _controller,
        readOnly: widget.readOnly,
        decoration: const InputDecoration(
          isDense: true,
          border: InputBorder.none,
          contentPadding: EdgeInsets.symmetric(horizontal: 6, vertical: 8),
        ),
        onChanged: widget.onChanged,
      ),
    );
  }
}
