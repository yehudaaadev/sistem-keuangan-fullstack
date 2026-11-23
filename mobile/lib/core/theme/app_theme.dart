import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Palet warna aplikasi: krem hangat, espresso, dan karamel.
class AppColors {
  AppColors._();

  // Solid Industrial Espresso Palette
  static const espresso = Color(0xFF2B1F17);
  static const espressoDeep = Color(0xFF19120D);
  static const coffee = Color(0xFF704A2D);
  static const latte = Color(0xFFA6713D);
  static const caramel = Color(0xFFC28135);

  // Surfaces & Backgrounds (Crisp, solid porcelain)
  static const cream = Color(0xFFF7F4EE);
  static const sand = Color(0xFFEDE5D8);
  static const white = Color(0xFFFFFFFF);
  static const surfaceSubtle = Color(0xFFF2ECE1);

  // High contrast typography & borders
  static const ink = Color(0xFF1C1612);
  static const muted = Color(0xFF675C53);
  static const line = Color(0xFFE2DACF);
  static const lineStrong = Color(0xFFC9BEAF);

  // Semantic Feedback (Solid, Enterprise POS Standard)
  static const success = Color(0xFF1B6B45);
  static const successSoft = Color(0xFFE7F4ED);
  static const successBorder = Color(0xFFA1D6BA);

  static const warning = Color(0xFF915904);
  static const warningSoft = Color(0xFFFDF3DF);
  static const warningBorder = Color(0xFFF4D493);

  static const danger = Color(0xFFB42318);
  static const dangerSoft = Color(0xFFFEECEB);
  static const dangerBorder = Color(0xFFF8B5AE);

  static const info = Color(0xFF1A5685);
  static const infoSoft = Color(0xFFEAF2F9);
  static const infoBorder = Color(0xFFA3C7E6);

  static const brandGradient = LinearGradient(
    colors: [espressoDeep, Color(0xFF423023)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const pageGradient = LinearGradient(
    colors: [Color(0xFFFAF7F2), Color(0xFFF3EDE3)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  // Subtle physical elevation (crisp, not fuzzy)
  static const softShadow = <BoxShadow>[
    BoxShadow(
      color: Color(0x0C000000),
      blurRadius: 10,
      offset: Offset(0, 3),
    ),
    BoxShadow(
      color: Color(0x08000000),
      blurRadius: 2,
      offset: Offset(0, 1),
    ),
  ];

  static const liftShadow = <BoxShadow>[
    BoxShadow(
      color: Color(0x18000000),
      blurRadius: 14,
      offset: Offset(0, 6),
    ),
    BoxShadow(
      color: Color(0x0A000000),
      blurRadius: 3,
      offset: Offset(0, 2),
    ),
  ];
}

/// Radius standar agar sudut seluruh aplikasi terasa konsisten.
class AppRadius {
  AppRadius._();

  static const small = 10.0;
  static const medium = 14.0;
  static const large = 18.0;
  static const xl = 24.0;

  static BorderRadius get allSmall => BorderRadius.circular(small);
  static BorderRadius get allMedium => BorderRadius.circular(medium);
  static BorderRadius get allLarge => BorderRadius.circular(large);
  static BorderRadius get allXl => BorderRadius.circular(xl);
}

/// Tema utama aplikasi.
class AppTheme {
  AppTheme._();

  static const ColorScheme _scheme = ColorScheme.light(
    primary: AppColors.espresso,
    onPrimary: AppColors.white,
    primaryContainer: AppColors.sand,
    onPrimaryContainer: AppColors.espressoDeep,
    secondary: AppColors.latte,
    onSecondary: AppColors.white,
    secondaryContainer: Color(0xFFF6E7D4),
    onSecondaryContainer: Color(0xFF5B3A16),
    tertiary: Color(0xFF3F6B5F),
    onTertiary: AppColors.white,
    tertiaryContainer: Color(0xFFDDEBE4),
    onTertiaryContainer: Color(0xFF21443B),
    error: AppColors.danger,
    onError: AppColors.white,
    errorContainer: AppColors.dangerSoft,
    onErrorContainer: Color(0xFF6C1610),
    surface: Color(0xFFFFFDFA),
    onSurface: AppColors.ink,
    surfaceContainerLowest: AppColors.white,
    surfaceContainerLow: Color(0xFFFBF7F1),
    surfaceContainer: Color(0xFFF6F0E8),
    surfaceContainerHigh: Color(0xFFF0E8DD),
    surfaceContainerHighest: Color(0xFFE9DFD2),
    onSurfaceVariant: AppColors.muted,
    outline: Color(0xFFD7C9B8),
    outlineVariant: AppColors.line,
    shadow: Color(0xFF2A1B10),
    scrim: Color(0x99000000),
    inverseSurface: Color(0xFF33261F),
    onInverseSurface: Color(0xFFF7EFE6),
    inversePrimary: Color(0xFFE3C7A6),
  );

  static ThemeData get light {
    final base = ThemeData(useMaterial3: true, colorScheme: _scheme);

    final textTheme = base.textTheme.copyWith(
      displaySmall: base.textTheme.displaySmall?.copyWith(
        fontWeight: FontWeight.w800,
        letterSpacing: -0.8,
        color: AppColors.ink,
      ),
      headlineMedium: base.textTheme.headlineMedium?.copyWith(
        fontWeight: FontWeight.w800,
        letterSpacing: -0.6,
        color: AppColors.ink,
      ),
      headlineSmall: base.textTheme.headlineSmall?.copyWith(
        fontWeight: FontWeight.w800,
        letterSpacing: -0.4,
        color: AppColors.ink,
      ),
      titleLarge: base.textTheme.titleLarge?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: -0.3,
        color: AppColors.ink,
      ),
      titleMedium: base.textTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: -0.1,
        color: AppColors.ink,
      ),
      titleSmall: base.textTheme.titleSmall?.copyWith(
        fontWeight: FontWeight.w700,
        color: AppColors.ink,
      ),
      bodyLarge: base.textTheme.bodyLarge?.copyWith(
        fontSize: 15,
        height: 1.45,
        color: AppColors.ink,
      ),
      bodyMedium: base.textTheme.bodyMedium?.copyWith(
        fontSize: 14,
        height: 1.45,
        color: AppColors.ink,
      ),
      bodySmall: base.textTheme.bodySmall?.copyWith(
        fontSize: 12,
        height: 1.4,
        color: AppColors.muted,
      ),
      labelLarge: base.textTheme.labelLarge?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w700,
      ),
      labelMedium: base.textTheme.labelMedium?.copyWith(
        fontSize: 12,
        fontWeight: FontWeight.w600,
      ),
      labelSmall: base.textTheme.labelSmall?.copyWith(
        fontSize: 11,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.2,
        color: AppColors.muted,
      ),
    );

    const buttonShape = RoundedRectangleBorder(
      borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
    );

    return base.copyWith(
      scaffoldBackgroundColor: AppColors.cream,
      canvasColor: AppColors.cream,
      splashFactory: InkSparkle.splashFactory,
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.cream,
        surfaceTintColor: Colors.transparent,
        foregroundColor: AppColors.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 16,
        toolbarHeight: 68,
        systemOverlayStyle: SystemUiOverlayStyle.dark,
        titleTextStyle: textTheme.titleLarge,
        iconTheme: const IconThemeData(color: AppColors.ink, size: 22),
      ),
      cardTheme: const CardThemeData(
        color: AppColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.large)),
          side: BorderSide(color: AppColors.line),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.xl)),
        ),
        titleTextStyle: textTheme.titleMedium?.copyWith(fontSize: 17.5),
        contentTextStyle: textTheme.bodyMedium?.copyWith(
          color: AppColors.muted,
        ),
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.white,
        surfaceTintColor: Colors.transparent,
        modalBarrierColor: Color(0x66000000),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.xl),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationThemeData(
        filled: true,
        fillColor: AppColors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        hintStyle: textTheme.bodyMedium?.copyWith(
          color: AppColors.muted.withValues(alpha: 0.75),
        ),
        labelStyle: textTheme.bodyMedium?.copyWith(color: AppColors.muted),
        floatingLabelStyle: textTheme.bodySmall?.copyWith(
          color: AppColors.espresso,
          fontWeight: FontWeight.w700,
        ),
        prefixIconColor: AppColors.muted,
        suffixIconColor: AppColors.muted,
        errorStyle: textTheme.bodySmall?.copyWith(color: AppColors.danger),
        border: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
          borderSide: BorderSide(color: AppColors.line),
        ),
        enabledBorder: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
          borderSide: BorderSide(color: AppColors.line),
        ),
        focusedBorder: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
          borderSide: BorderSide(color: AppColors.espresso, width: 1.6),
        ),
        errorBorder: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
          borderSide: BorderSide(color: AppColors.danger),
        ),
        focusedErrorBorder: const OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
          borderSide: BorderSide(color: AppColors.danger, width: 1.6),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.espresso,
          foregroundColor: AppColors.white,
          disabledBackgroundColor: AppColors.line,
          disabledForegroundColor: AppColors.muted,
          elevation: 0,
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: buttonShape,
          textStyle: textTheme.labelLarge,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.white,
          foregroundColor: AppColors.espresso,
          elevation: 0,
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: buttonShape,
          textStyle: textTheme.labelLarge,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.espresso,
          minimumSize: const Size(0, 44),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          side: const BorderSide(color: AppColors.line),
          shape: buttonShape,
          textStyle: textTheme.labelLarge,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.espresso,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(AppRadius.small)),
          ),
          textStyle: textTheme.labelLarge,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: AppColors.ink,
          highlightColor: AppColors.sand,
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.espresso,
        foregroundColor: AppColors.white,
        elevation: 2,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.white,
        selectedColor: AppColors.espresso,
        secondarySelectedColor: AppColors.espresso,
        disabledColor: AppColors.sand,
        side: const BorderSide(color: AppColors.line),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.small)),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        labelStyle: textTheme.bodySmall?.copyWith(
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          color: AppColors.ink,
        ),
        secondaryLabelStyle: textTheme.bodySmall?.copyWith(
          fontSize: 12.5,
          fontWeight: FontWeight.w700,
          color: AppColors.white,
        ),
        showCheckmark: false,
        elevation: 0,
        pressElevation: 0,
      ),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: ButtonStyle(
          backgroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.selected)
                ? AppColors.espresso
                : AppColors.white,
          ),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.selected)
                ? AppColors.white
                : AppColors.muted,
          ),
          side: const WidgetStatePropertyAll(BorderSide(color: AppColors.line)),
          shape: const WidgetStatePropertyAll(
            RoundedRectangleBorder(
              borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
            ),
          ),
          padding: const WidgetStatePropertyAll(
            EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          ),
          textStyle: WidgetStatePropertyAll(
            textTheme.bodySmall?.copyWith(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
            ),
          ),
          iconSize: const WidgetStatePropertyAll(18),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.line,
        thickness: 1,
        space: 1,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.espressoDeep,
        contentTextStyle: textTheme.bodyMedium?.copyWith(
          color: AppColors.white,
        ),
        actionTextColor: AppColors.caramel,
        elevation: 6,
        insetPadding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
        ),
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: AppColors.espressoDeep,
          borderRadius: BorderRadius.circular(8),
        ),
        textStyle: textTheme.bodySmall?.copyWith(color: AppColors.white),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      ),
      listTileTheme: const ListTileThemeData(
        iconColor: AppColors.muted,
        textColor: AppColors.ink,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.espresso,
        linearTrackColor: AppColors.sand,
        circularTrackColor: AppColors.sand,
      ),
      popupMenuTheme: const PopupMenuThemeData(
        color: AppColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 8,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.medium)),
        ),
      ),
      scrollbarTheme: const ScrollbarThemeData(
        thumbColor: WidgetStatePropertyAll(Color(0x33000000)),
        radius: Radius.circular(8),
      ),
      splashColor: AppColors.sand.withValues(alpha: 0.6),
      highlightColor: Colors.transparent,
    );
  }
}
