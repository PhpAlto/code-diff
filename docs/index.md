# Alto Code Diff

Alto Code Diff compares text, renders structured differences, and parses, emits, or applies unified patches. It works with strings: reading and writing files remains the responsibility of your application.

## Introduction

- [Installation](installation.md) installs the package and lists its requirements.
- [Getting started](getting-started.md) compares two strings and renders the result.

## Diffing

- [Comparison](comparison.md) covers options, word-level changes, limits, and the result model.
- [Rendering](rendering.md) produces unified text, HTML, JSON, or ANSI output.
- [Engines](engines.md) explains the built-in algorithms and extension points.

## Patches

- [Patches](patches.md) parses, emits, and applies single-file or multi-file unified patches.

The package rejects binary input and does not read files, execute Git, or resolve patch conflicts automatically.
