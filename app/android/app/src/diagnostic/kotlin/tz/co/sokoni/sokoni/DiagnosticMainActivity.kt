package tz.co.sokoni.sokoni

import android.app.Activity
import android.content.ContentUris
import android.content.ContentValues
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.MediaStore
import android.view.Gravity
import android.widget.TextView
import io.flutter.embedding.android.FlutterView
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.engine.dart.DartExecutor
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodChannel
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Diagnostic-only launcher (see DECISIONS.md): registers every plugin
 * `GeneratedPluginRegistrant` would register, but one at a time in a fixed
 * order, writing the plugin's name to the screen and to a log file
 * *before* each attempt. Whichever plugin never gets past that point is
 * the culprit — its name is the last thing on screen and the last line in
 * the log when it freezes.
 *
 * This needs to survive an actual hang with no debugger/adb access (the
 * test device's developer options are locked by a device-financing app),
 * so both the on-screen label and the log file write happen, with a short
 * delay to let the screen actually paint, *before* the risky call — not
 * after — so they reflect the step in progress rather than the last one
 * that finished.
 *
 * A plain [Activity], not a [io.flutter.embedding.android.FlutterActivity]:
 * full manual control over exactly what's on screen and when matters more
 * here than Flutter's usual lifecycle convenience. If every step
 * registers successfully, this hands off to a real [FlutterView] and
 * executes whatever Dart entrypoint the build was given via `-t`
 * (`DartExecutor.DartEntrypoint.createDefault()` resolves that at build
 * time) — `lib/main_diagnostic.dart` for a plugin-registration-only pass,
 * or the real `lib/main.dart` to continue the bisection into the app's
 * own startup path once plugin registration is cleared as a suspect (see
 * DECISIONS.md). Either way, [LOG_CHANNEL] lets Dart-side code (see
 * `BootLog` in the Dart source) keep appending to the same log file after
 * the handoff.
 */
class DiagnosticMainActivity : Activity() {
    private lateinit var statusText: TextView
    private lateinit var engine: FlutterEngine
    private lateinit var steps: List<Pair<String, () -> FlutterPlugin>>
    private var stepIndex = 0
    private var logUri: Uri? = null

    private val mainHandler = Handler(Looper.getMainLooper())
    private val timeFormat = SimpleDateFormat("HH:mm:ss.SSS", Locale.US)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        statusText = TextView(this).apply {
            textSize = 18f
            setPadding(48, 160, 48, 48)
            gravity = Gravity.START
            setTextColor(Color.WHITE)
            setBackgroundColor(Color.BLACK)
            text = "Sokoni diagnostic\n\nStarting…"
        }
        setContentView(statusText)

        logUri = findOrCreateLogUri()
        appendLog("=== run started ===")

        // Give the "Starting…" frame a chance to actually paint before
        // doing anything that might block — see runNextStep for why every
        // subsequent step does the same before its risky call.
        mainHandler.postDelayed({ startBisection() }, 50)
    }

    private fun startBisection() {
        engine = FlutterEngine(this)
        appendLog("FlutterEngine constructed OK")
        steps = buildStepList()
        stepIndex = 0
        runNextStep()
    }

    private fun runNextStep() {
        if (stepIndex >= steps.size) {
            val msg = "ALL ${steps.size} PLUGINS REGISTERED"
            statusText.text = "Sokoni diagnostic\n\n$msg\n\nHanding off to Flutter…"
            appendLog("=== $msg — starting Dart entrypoint ===")
            mainHandler.postDelayed({ launchFlutterUi() }, 50)
            return
        }

        val (name, factory) = steps[stepIndex]
        val label = "Registering (${stepIndex + 1}/${steps.size}):\n$name"
        statusText.text = "Sokoni diagnostic\n\n$label"
        appendLog("about to register: $name")

        // The actual registration call is what might hang — deliberately
        // scheduled after the label above is set and logged, and after a
        // delay long enough for the UI thread to paint that frame first.
        // If this specific step is the one that hangs, this is the last
        // line the log file and the screen will ever show.
        mainHandler.postDelayed({
            try {
                engine.plugins.add(factory())
                appendLog("  -> $name OK")
            } catch (e: Throwable) {
                appendLog("  -> $name THREW: $e")
                statusText.text = "Sokoni diagnostic\n\n$name THREW:\n$e"
            }
            stepIndex++
            runNextStep()
        }, 50)
    }

    private fun launchFlutterUi() {
        val flutterView = FlutterView(this)
        flutterView.attachToFlutterEngine(engine)
        setContentView(flutterView)

        // Lets the real app's Dart code (BootLog, see main.dart) keep
        // appending to this same log file once control passes to Dart —
        // the whole point of building this with `-t lib/main.dart` instead
        // of the plugin-only `main_diagnostic.dart`: bisection proved every
        // plugin registers fine, so the remaining hang must be somewhere
        // in the real app's own startup path, after this point.
        //
        // Logs its own attachment and every message it ever receives, with
        // content — a previous run showed all 22 plugins registering, then
        // total silence with no Dart-side log lines at all, so this run
        // needs to prove the channel itself works rather than assume it.
        MethodChannel(engine.dartExecutor.binaryMessenger, LOG_CHANNEL).setMethodCallHandler { call, result ->
            appendLog("CHANNEL RECEIVED method=${call.method} arguments=${call.arguments}")
            if (call.method == "log") {
                val line = call.argument<String>("line") ?: ""
                appendLog("[dart] $line")
                result.success(null)
            } else {
                result.notImplemented()
            }
        }
        appendLog("Dart channel handler attached")

        // Confirm exactly what this build is actually about to execute —
        // a stale or mismatched entrypoint would explain silence just as
        // well as a crash would.
        val entrypoint = DartExecutor.DartEntrypoint.createDefault()
        appendLog(
            "about to executeDartEntrypoint: pathToBundle=${entrypoint.pathToBundle} " +
                "library=${entrypoint.dartEntrypointLibrary} function=${entrypoint.dartEntrypointFunctionName}"
        )
        engine.dartExecutor.executeDartEntrypoint(entrypoint)
        appendLog("executeDartEntrypoint() call returned")
    }

    /**
     * Order matters: everything low-suspicion first, the named suspects
     * last — geolocator, flutter_secure_storage, video_compress,
     * firebase_core, firebase_messaging, google_sign_in,
     * sign_in_with_apple. Class names copied from the generated
     * `GeneratedPluginRegistrant.java` (do not edit that file directly —
     * it's regenerated on every build).
     *
     * `google_maps_flutter` was on this list at the time of the original
     * investigation but was removed from the project entirely in a later
     * round (replaced with `flutter_map`, tester feedback B3 — see
     * DECISIONS.md) — its plugin class no longer exists in the dependency
     * tree at all, so the reference here was dropped rather than left as
     * dead code that breaks every build of this flavour.
     */
    private fun buildStepList(): List<Pair<String, () -> FlutterPlugin>> {
        return listOf(
            "connectivity_plus" to { dev.fluttercommunity.plus.connectivity.ConnectivityPlugin() },
            "file_picker" to { com.mr.flutter.plugin.filepicker.FilePickerPlugin() },
            "flutter_image_compress_common" to { com.fluttercandies.flutter_image_compress.ImageCompressPlugin() },
            "flutter_plugin_android_lifecycle" to { io.flutter.plugins.flutter_plugin_android_lifecycle.FlutterAndroidLifecyclePlugin() },
            "geocoding_android" to { com.baseflow.geocoding.GeocodingPlugin() },
            "image_picker_android" to { io.flutter.plugins.imagepicker.ImagePickerPlugin() },
            "jni" to { com.github.dart_lang.jni.JniPlugin() },
            "jni_flutter" to { com.github.dart_lang.jni_flutter.JniFlutterPlugin() },
            "package_info_plus" to { dev.fluttercommunity.plus.packageinfo.PackageInfoPlugin() },
            "share_plus" to { dev.fluttercommunity.plus.share.SharePlusPlugin() },
            "sqflite_android" to { com.tekartik.sqflite.SqflitePlugin() },
            "url_launcher_android" to { io.flutter.plugins.urllauncher.UrlLauncherPlugin() },
            "video_player_android" to { io.flutter.plugins.videoplayer.VideoPlayerPlugin() },
            "wakelock_plus" to { dev.fluttercommunity.plus.wakelock.WakelockPlusPlugin() },
            // Named suspects, deliberately last.
            "geolocator_android" to { com.baseflow.geolocator.GeolocatorPlugin() },
            "flutter_secure_storage" to { com.it_nomads.fluttersecurestorage.FlutterSecureStoragePlugin() },
            "video_compress" to { com.example.video_compress.VideoCompressPlugin() },
            "firebase_core" to { io.flutter.plugins.firebase.core.FlutterFirebaseCorePlugin() },
            "firebase_messaging" to { io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingPlugin() },
            "google_sign_in_android" to { io.flutter.plugins.googlesignin.GoogleSignInPlugin() },
            "sign_in_with_apple" to { com.aboutyou.dart_packages.sign_in_with_apple.SignInWithApplePlugin() },
        )
    }

    // MediaStore Downloads logging — Android 10+'s scoped storage lets an
    // app write its own files under Downloads with no storage permission
    // at all, which is what makes this reachable via the phone's own
    // Files app without adb. Reuses the same row across relaunches
    // (queried by display name) rather than letting MediaStore mint
    // "sokoni-boot (1).txt", "(2).txt", etc. on every retry.
    private fun findOrCreateLogUri(): Uri? {
        return try {
            val resolver = contentResolver
            val projection = arrayOf(MediaStore.Downloads._ID)
            val selection = "${MediaStore.Downloads.DISPLAY_NAME} = ?"
            val args = arrayOf(LOG_FILE_NAME)
            resolver.query(MediaStore.Downloads.EXTERNAL_CONTENT_URI, projection, selection, args, null)?.use { cursor ->
                if (cursor.moveToFirst()) {
                    val id = cursor.getLong(cursor.getColumnIndexOrThrow(MediaStore.Downloads._ID))
                    return ContentUris.withAppendedId(MediaStore.Downloads.EXTERNAL_CONTENT_URI, id)
                }
            }
            val values = ContentValues().apply {
                put(MediaStore.Downloads.DISPLAY_NAME, LOG_FILE_NAME)
                put(MediaStore.Downloads.MIME_TYPE, "text/plain")
            }
            resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
        } catch (e: Throwable) {
            null
        }
    }

    // Opened and flushed fresh on every single line ("wa" = write+append,
    // MediaStore's documented mode for appending to an existing item)
    // rather than held open for the run, so a freeze on the very next
    // step still leaves everything logged so far durably on disk.
    private fun appendLog(line: String) {
        val uri = logUri ?: return
        try {
            contentResolver.openOutputStream(uri, "wa")?.use { out ->
                out.write("${timeFormat.format(Date())}  $line\n".toByteArray())
                out.flush()
            }
        } catch (e: Throwable) {
            // Best-effort — a logging failure isn't the bug under test.
        }
    }

    companion object {
        private const val LOG_FILE_NAME = "sokoni-boot.txt"
        private const val LOG_CHANNEL = "sokoni.diagnostic/log"
    }
}
