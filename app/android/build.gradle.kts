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
}
subprojects {
    project.evaluationDependsOn(":app")
}

// A `subprojects { afterEvaluate { ... } }` block forcing every subproject
// onto a consistent JVM 17 target used to live here, worked around an
// "Inconsistent JVM Target Compatibility" failure caused specifically by
// flutter_facebook_auth 6.0.4's Android build.gradle (no explicit
// compileOptions/kotlinOptions, so its Java compilation defaulted to JVM
// 1.8 while its Kotlin compilation picked up the JDK actually running the
// build). Confirmed by removing the workaround and doing a clean rebuild
// after removing that package (see DECISIONS.md) — it now builds with only
// harmless "source value 8 is obsolete" warnings from whichever plugins
// still don't set compileOptions explicitly, not a hard failure, so the
// workaround was specific to that one package and not needed generally.

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
