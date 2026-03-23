# Minimal ProGuard rules - keep Firebase and Glide/OkHttp classes used by plugins
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-keepattributes Signature
-keepattributes *Annotation*
-keepclassmembers class * {
    @com.google.firebase.database.PropertyName <methods>;
}
# Keep generated model classes
-keepclassmembers class * {
    public <init>(...);
}
