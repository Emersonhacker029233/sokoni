allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)

    // Several plugins (e.g. flutter_facebook_auth 6.0.4) ship an Android
    // build.gradle with no explicit compileOptions/kotlinOptions — Gradle
    // then defaults their Java compilation to JVM 1.8 while the Kotlin
    // compiler task picks up whatever JVM is actually running the build
    // (21, via Android Studio's bundled JDK on this machine), which fails
    // as an "Inconsistent JVM Target Compatibility" error. Force every
    // subproject (app included) onto a single consistent JVM 17 target
    // rather than patching each outdated plugin template individually.
    // Must be registered here, before evaluationDependsOn(":app") below
    // forces early evaluation — afterEvaluate errors on an already-
    // evaluated project.
    afterEvaluate {
        extensions.findByType<com.android.build.gradle.BaseExtension>()?.apply {
            compileOptions {
                sourceCompatibility = JavaVersion.VERSION_17
                targetCompatibility = JavaVersion.VERSION_17
            }
        }
        tasks.withType<org.jetbrains.kotlin.gradle.tasks.KotlinCompile>().configureEach {
            compilerOptions {
                jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17)
            }
        }
    }
}
subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
