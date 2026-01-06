---
title: Shortcode Demo
layout: page
category: docs/examples
description: "Demonstration of StaticForge's powerful Shortcode system, showing how to easily embed dynamic content like YouTube videos and more."
url: "https://calevans.com/staticforge/examples/docs-examples/shortcodes.html"
og_image: "Magic wand casting code snippets, keyboard shortcut keys, automation sparks, wizardry in coding, --ar 16:9"
---

This page demonstrates the new Shortcode system.

## Youtube Shortcode

Here is a video:

[[youtube id="dQw4w9WgXcQ" title="Rick Roll"]]

## Alert Shortcode

[[alert type="info"]]
This is an **info** alert with markdown support.
[[/alert]]

[[alert type="warning"]]
This is a **warning** alert!
[[/alert]]

[[alert type="error"]]
This is an **error** alert.
[[/alert]]

[[alert type="success"]]
This is a **success** alert.
[[/alert]]

## Weather Shortcode

Weather in West Palm Beach, FL (using Zip, Fahrenheit):
[[weather zip="33409" country="us" scale="F"]]

Weather in London (using Lat/Long, Celsius):
[[weather lat="51.5074" long="-0.1278" scale="C"]]

## Escaping

This should show the shortcode text, not render it:
[[[youtube id="123"]]]
