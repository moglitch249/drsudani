# ============================================================
# DrSudani App - ProGuard Rules
# Prevents reverse engineering of the release APK
# ============================================================

# Keep Flutter engine
-keep class io.flutter.** { *; }
-keep class io.flutter.embedding.** { *; }
-dontwarn io.flutter.**

# Keep Kotlin metadata
-keep class kotlin.** { *; }
-dontwarn kotlin.**

# Keep WooCommerce / API models (prevent stripping)
-keepattributes *Annotation*
-keepattributes Signature
-keepattributes Exceptions

# Hive (local encrypted storage)
-keep class com.hivedb.** { *; }

# OkHttp / Dio networking
-dontwarn okhttp3.**
-dontwarn okio.**
-keep class okhttp3.** { *; }

# Remove all debug logs from release builds
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
    public static *** w(...);
    public static *** e(...);
}

# Prevent class name exposure in stack traces
-renamesourcefileattribute SourceFile
-keepattributes SourceFile,LineNumberTable
