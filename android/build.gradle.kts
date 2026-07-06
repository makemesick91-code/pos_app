// Top-level build file. Common configuration lives in each module's build.gradle.kts.
plugins {
    id("com.android.application") version "8.7.3" apply false
    id("org.jetbrains.kotlin.android") version "2.0.21" apply false
    // KSP powers Room's annotation processing (Sprint 3 local catalog).
    id("com.google.devtools.ksp") version "2.0.21-1.0.28" apply false
}
