import 'package:flutter/material.dart';

class CustomButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final ButtonStyle? style;
  final IconData? icon;
  final bool outlined;
  final double? width;
  final double? height;

  const CustomButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.style,
    this.icon,
    this.outlined = false,
    this.width,
    this.height,
  });

  const CustomButton.outlined({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.style,
    this.icon,
    this.width,
    this.height,
  }) : outlined = true;

  @override
  Widget build(BuildContext context) {
    Widget child = isLoading
        ? SizedBox(
            height: 20,
            width: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              valueColor: AlwaysStoppedAnimation<Color>(
                outlined 
                  ? Theme.of(context).colorScheme.primary 
                  : Colors.white,
              ),
            ),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(icon),
                const SizedBox(width: 8),
              ],
              Text(text),
            ],
          );

    Widget button = outlined
        ? OutlinedButton(
            onPressed: isLoading ? null : onPressed,
            style: style,
            child: child,
          )
        : ElevatedButton(
            onPressed: isLoading ? null : onPressed,
            style: style,
            child: child,
          );

    if (width != null || height != null) {
      return SizedBox(
        width: width,
        height: height ?? 48,
        child: button,
      );
    }

    return SizedBox(
      width: double.infinity,
      height: height ?? 48,
      child: button,
    );
  }
}
