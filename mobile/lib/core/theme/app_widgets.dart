import 'package:flutter/material.dart';

import 'app_theme.dart';

/// Logo kecil aplikasi berupa kotak bergradasi.
class BrandMark extends StatelessWidget {
  final double size;
  final IconData icon;
  final bool shadow;

  const BrandMark({
    super.key,
    this.size = 44,
    this.icon = Icons.local_cafe_rounded,
    this.shadow = true,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: AppColors.brandGradient,
        borderRadius: BorderRadius.circular(size * 0.32),
        boxShadow: shadow
            ? [
                BoxShadow(
                  color: AppColors.espresso.withValues(alpha: 0.26),
                  blurRadius: size * 0.45,
                  offset: Offset(0, size * 0.16),
                ),
              ]
            : null,
      ),
      child: Icon(icon, color: AppColors.white, size: size * 0.48),
    );
  }
}

/// Tombol ikon bulat dengan latar putih dan garis tipis.
class CircleActionButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onPressed;
  final String? tooltip;
  final Color? iconColor;

  const CircleActionButton({
    super.key,
    required this.icon,
    required this.onPressed,
    this.tooltip,
    this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    final button = Material(
      color: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
        side: BorderSide(color: AppColors.line),
      ),
      child: InkWell(
        onTap: onPressed,
        borderRadius: AppRadius.allMedium,
        child: SizedBox(
          width: 42,
          height: 42,
          child: Icon(icon, size: 20, color: iconColor ?? AppColors.espresso),
        ),
      ),
    );

    if (tooltip == null) return button;
    return Tooltip(message: tooltip!, child: button);
  }
}

/// Label status kecil dengan titik/ikon berwarna.
class StatusPill extends StatelessWidget {
  final String label;
  final Color color;
  final Color background;
  final IconData? icon;
  final bool dot;

  const StatusPill({
    super.key,
    required this.label,
    required this.color,
    required this.background,
    this.icon,
    this.dot = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (dot) ...[
            Container(
              width: 7,
              height: 7,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 6),
          ],
          if (icon != null) ...[
            Icon(icon, size: 13, color: color),
            const SizedBox(width: 5),
          ],
          Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              color: color,
              letterSpacing: 0.1,
            ),
          ),
        ],
      ),
    );
  }
}

enum BannerTone { info, success, warning, danger }

/// Kotak pesan berwarna lembut untuk info/error di dalam form.
class InfoBanner extends StatelessWidget {
  final String message;
  final BannerTone tone;
  final IconData? icon;

  const InfoBanner({
    super.key,
    required this.message,
    this.tone = BannerTone.info,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    final (color, background, border, defaultIcon) = switch (tone) {
      BannerTone.info => (
        AppColors.info,
        AppColors.infoSoft,
        AppColors.infoBorder,
        Icons.info_outline_rounded,
      ),
      BannerTone.success => (
        AppColors.success,
        AppColors.successSoft,
        AppColors.successBorder,
        Icons.check_circle_outline_rounded,
      ),
      BannerTone.warning => (
        AppColors.warning,
        AppColors.warningSoft,
        AppColors.warningBorder,
        Icons.warning_amber_rounded,
      ),
      BannerTone.danger => (
        AppColors.danger,
        AppColors.dangerSoft,
        AppColors.dangerBorder,
        Icons.error_outline_rounded,
      ),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: background,
        borderRadius: AppRadius.allMedium,
        border: Border.all(color: border, width: 1.2),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon ?? defaultIcon, size: 19, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 12.5,
                height: 1.45,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Kartu dasar aplikasi: permukaan solid, sudut presisi, garis tegas, tanpa efek transparan.
class SurfaceCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final Color? color;
  final Color? borderColor;
  final double borderWidth;
  final BorderRadius? borderRadius;
  final VoidCallback? onTap;
  final bool elevated;

  const SurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.margin,
    this.color,
    this.borderColor,
    this.borderWidth = 1.0,
    this.borderRadius,
    this.onTap,
    this.elevated = false,
  });

  @override
  Widget build(BuildContext context) {
    final radius = borderRadius ?? AppRadius.allLarge;
    final content = AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOutCubic,
      decoration: BoxDecoration(
        color: color ?? AppColors.white,
        borderRadius: radius,
        border: Border.all(
          color: borderColor ?? AppColors.line,
          width: borderWidth,
        ),
        boxShadow: elevated ? AppColors.softShadow : null,
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: radius,
        child: InkWell(
          onTap: onTap,
          borderRadius: radius,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );

    return margin == null ? content : Padding(padding: margin!, child: content);
  }
}

/// Gagang kecil di bagian atas bottom sheet.
class SheetHandle extends StatelessWidget {
  final EdgeInsets padding;

  const SheetHandle({
    super.key,
    this.padding = const EdgeInsets.only(top: 10, bottom: 4),
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: Center(
        child: Container(
          width: 44,
          height: 5,
          decoration: BoxDecoration(
            color: AppColors.line,
            borderRadius: BorderRadius.circular(999),
          ),
        ),
      ),
    );
  }
}

/// Chip kategori berbentuk pil.
class CategoryPill extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final IconData? icon;

  const CategoryPill({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.espresso : AppColors.white,
      shape: const StadiumBorder(side: BorderSide(color: AppColors.line)),
      child: InkWell(
        onTap: onTap,
        customBorder: const StadiumBorder(),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(
                  icon,
                  size: 15,
                  color: selected ? AppColors.white : AppColors.muted,
                ),
                const SizedBox(width: 6),
              ],
              Text(
                label,
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                  color: selected ? AppColors.white : AppColors.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Tampilan kosong dengan ikon di dalam lingkaran lembut.
class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final Color? iconColor;
  final Color? iconBackground;

  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
    this.iconColor,
    this.iconBackground,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: iconBackground ?? AppColors.sand,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 28, color: iconColor ?? AppColors.coffee),
            ),
            const SizedBox(height: 14),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
            ),
            if (message != null) ...[
              const SizedBox(height: 4),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12.5,
                  height: 1.45,
                  color: AppColors.muted,
                ),
              ),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 16),
              OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}

/// Baris ringkasan label–nilai di panel pesanan dan struk.
class SummaryRow extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  final bool discount;
  final Color? valueColor;

  const SummaryRow({
    super.key,
    required this.label,
    required this.value,
    this.bold = false,
    this.discount = false,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                color: bold ? AppColors.ink : AppColors.muted,
                fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            value,
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
              color:
                  valueColor ??
                  (discount
                      ? AppColors.success
                      : (bold ? AppColors.ink : AppColors.ink)),
            ),
          ),
        ],
      ),
    );
  }
}

/// Stepper jumlah (bulat, ringkas) untuk daftar item pesanan.
class QtyStepper extends StatelessWidget {
  final double qty;
  final VoidCallback onMinus;
  final VoidCallback onPlus;
  final bool compact;

  const QtyStepper({
    super.key,
    required this.qty,
    required this.onMinus,
    required this.onPlus,
    this.compact = true,
  });

  @override
  Widget build(BuildContext context) {
    final size = compact ? 26.0 : 32.0;

    Widget button(IconData icon, VoidCallback onTap) {
      return Material(
        color: AppColors.white,
        shape: const CircleBorder(side: BorderSide(color: AppColors.line)),
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: SizedBox(
            width: size,
            height: size,
            child: Icon(
              icon,
              size: compact ? 14 : 17,
              color: AppColors.espresso,
            ),
          ),
        ),
      );
    }

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        button(Icons.remove_rounded, onMinus),
        SizedBox(
          width: compact ? 34 : 44,
          child: Text(
            _qtyText(qty),
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: compact ? 13 : 15,
              fontWeight: FontWeight.w800,
              color: AppColors.ink,
            ),
          ),
        ),
        button(Icons.add_rounded, onPlus),
      ],
    );
  }

  static String _qtyText(double value) {
    if (value == value.roundToDouble()) return value.toInt().toString();
    return value.toString();
  }
}
