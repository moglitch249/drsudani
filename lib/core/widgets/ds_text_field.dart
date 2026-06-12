import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../theme/app_dimensions.dart';

class DsTextField extends StatefulWidget {
  final String? label;
  final String hint;
  final TextEditingController? controller;
  final FormFieldValidator<String>? validator;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final bool obscureText;
  final TextInputType keyboardType;
  final TextDirection? textDirection;
  final ValueChanged<String>? onChanged;

  const DsTextField({
    Key? key,
    this.label,
    required this.hint,
    this.controller,
    this.validator,
    this.prefixIcon,
    this.suffixIcon,
    this.obscureText = false,
    this.keyboardType = TextInputType.text,
    this.textDirection,
    this.onChanged,
  }) : super(key: key);

  @override
  State<DsTextField> createState() => _DsTextFieldState();
}

class _DsTextFieldState extends State<DsTextField> {
  bool _isFocused = false;

  @override
  Widget build(BuildContext context) {
    final isArabic = Directionality.of(context) == TextDirection.rtl;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (widget.label != null) ...[
          Text(
            widget.label!,
            style: Theme.of(context).textTheme.labelLarge,
          ),
          const SizedBox(height: AppDimensions.sm),
        ],
        Container(
          decoration: BoxDecoration(
            boxShadow: _isFocused ? [AppDimensions.primaryGlow] : null,
            borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
          ),
          child: Focus(
            onFocusChange: (hasFocus) {
              setState(() => _isFocused = hasFocus);
            },
            child: TextFormField(
              controller: widget.controller,
              validator: widget.validator,
              obscureText: widget.obscureText,
              keyboardType: widget.keyboardType,
              textDirection: widget.textDirection,
              onChanged: widget.onChanged,
              textAlign: isArabic ? TextAlign.right : TextAlign.left,
              style: Theme.of(context).textTheme.bodyLarge,
              decoration: InputDecoration(
                hintText: widget.hint,
                hintStyle: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: AppTheme.textMuted,
                    ),
                filled: true,
                fillColor: Theme.of(context).brightness == Brightness.dark ? AppTheme.surfaceVariant : AppTheme.surfaceVariantLight,
                prefixIcon: widget.prefixIcon,
                suffixIcon: widget.suffixIcon,
                contentPadding: const EdgeInsets.all(AppDimensions.md),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                  borderSide: BorderSide.none,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                  borderSide: BorderSide.none,
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                  borderSide: const BorderSide(color: AppTheme.primary, width: 1.5),
                ),
                errorBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                  borderSide: const BorderSide(color: AppTheme.error, width: 1.5),
                ),
                focusedErrorBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                  borderSide: const BorderSide(color: AppTheme.error, width: 1.5),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
