# UIX-2 Performance

The rendered homepage HTML is approximately 45 KB before transport compression. It contains no raster product assets, external fonts, third-party requests, framework runtime, video, or autoplay media. JavaScript is a small inline enhancement for menu and tabs; FAQ works natively. Layout dimensions are CSS-defined to reduce shifts.

Chrome headless rendered all required viewports successfully. Lighthouse scores are not claimed because Lighthouse was unavailable. The implementation preserves the zero-build Blade deployment and is expected to remain materially below the five-second target on the controlled pilot, subject to runtime measurement.
