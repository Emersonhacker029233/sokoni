import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    // Applies google-services.json (present in this directory) at build
    // time so Firebase (FCM push) actually initialises.
    id("com.google.gms.google-services")
}

// key.properties (gitignored — see root .gitignore) holds the release
// keystore credentials. Falls back to debug signing when absent, so
// `flutter run`/`flutter build` still work for anyone without the
// keystore (e.g. a fresh checkout) — only the release build needs it.
val keystorePropertiesFile = rootProject.file("key.properties")
val keystoreProperties = Properties()
val hasKeystoreProperties = keystorePropertiesFile.exists()
if (hasKeystoreProperties) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

android {
    namespace = "tz.co.sokoni.sokoni"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "tz.co.sokoni.sokoni"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (hasKeystoreProperties) {
            create("release") {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                storeFile = rootProject.file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            signingConfig = if (hasKeystoreProperties) {
                signingConfigs.getByName("release")
            } else {
                // No key.properties present (e.g. a fresh checkout without
                // the keystore) — fall back to debug signing so the build
                // still succeeds, matching Flutter's own scaffold default.
                signingConfigs.getByName("debug")
            }
        }
    }

    // "diagnostic" isolates whether Flutter's engine itself boots on a
    // device where the full app hangs before ever reaching Dart (see
    // DECISIONS.md) — a separate applicationId so it installs alongside
    // the real app rather than replacing it. "prod" is the real app,
    // unchanged in every respect (same applicationId/output) from before
    // these flavors existed — every future build command just needs an
    // explicit `--flavor prod` now that any flavor is declared at all.
    flavorDimensions += "environment"
    productFlavors {
        create("prod") {
            dimension = "environment"
        }
        create("diagnostic") {
            dimension = "environment"
            applicationIdSuffix = ".diagnostic"
            versionNameSuffix = "-diagnostic"
        }
    }
}

flutter {
    source = "../.."
}

// The Google Services plugin processes every build variant by default and
// fails the build outright if google-services.json has no client entry
// for that variant's applicationId — true for every "diagnostic" variant,
// since applicationIdSuffix changes the package name and the diagnostic
// flavor deliberately runs with no Firebase config at all (its manifest
// also strips Firebase's own auto-init provider — see DECISIONS.md).
// Disabling the task for that flavor is simpler and less fragile than
// fabricating a second client entry in a file that otherwise holds real
// production credentials.
tasks.configureEach {
    if (name.contains("GoogleServices") && name.contains("Diagnostic", ignoreCase = true)) {
        enabled = false
    }
}
