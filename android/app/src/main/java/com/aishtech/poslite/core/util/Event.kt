package com.aishtech.poslite.core.util

import androidx.lifecycle.Observer

/**
 * A one-time event wrapper for LiveData (UIX8B-R008 / UIX8-R013).
 *
 * Screen ViewModels expose one-time outcomes (navigate-to-receipt, show-toast,
 * login-success) as `LiveData<Event<T>>`. The value is consumed exactly once:
 * after [getContentIfNotHandled] returns it, a configuration change or process
 * recreation that re-delivers the same sticky LiveData value will NOT re-fire the
 * event (the second read returns null). This prevents duplicate navigation, a
 * replayed toast, or — critically — a re-shown "transaction success" after
 * rotation. Persistent screen STATE stays as ordinary LiveData; only genuine
 * one-shot signals use this wrapper.
 */
class Event<out T>(private val content: T) {

    var hasBeenHandled = false
        private set

    /** Returns the content once; subsequent calls (e.g. after rotation) return null. */
    fun getContentIfNotHandled(): T? =
        if (hasBeenHandled) {
            null
        } else {
            hasBeenHandled = true
            content
        }

    /** Reads the content without consuming it (e.g. for logging/inspection). */
    fun peekContent(): T = content
}

/**
 * Observer that unwraps an [Event] and only invokes [onEvent] when the content
 * has not already been handled.
 */
class EventObserver<T>(private val onEvent: (T) -> Unit) : Observer<Event<T>> {
    override fun onChanged(value: Event<T>) {
        value.getContentIfNotHandled()?.let(onEvent)
    }
}
