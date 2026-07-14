package com.aishtech.poslite

import java.io.File

/**
 * Resolves the app res/ directories for pure-JVM resource-invariant tests
 * (UIX-8C-02). Gradle runs unit tests with the module dir as the working dir,
 * but this walks a few candidate roots so the tests are robust to the runner.
 */
object ResPaths {
    private fun resDir(): File {
        val candidates = listOf(
            "src/main/res",
            "android/app/src/main/res",
            "app/src/main/res",
        )
        val start = File("").absoluteFile
        // Try relative to cwd and up to 4 parents.
        var base: File? = start
        repeat(5) {
            val b = base ?: return@repeat
            candidates.forEach { c ->
                val f = File(b, c)
                if (f.isDirectory) return f
            }
            base = b.parentFile
        }
        error("could not locate res/ dir from ${start.absolutePath}")
    }

    fun valuesDir(): File = File(resDir(), "values")
    fun layoutDir(): File = File(resDir(), "layout")
    fun layout(name: String): File = File(layoutDir(), name)
}
