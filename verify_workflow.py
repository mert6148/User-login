#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Hızlı test doğrulama aracı (CLI).

Kullanım örnekleri:
  - Tüm hızlı testleri çalıştır: python verify_workflow.py --all
  - Tekil doğrulama: python verify_workflow.py --single profile email test@example.com
  - JSON çıktı: python verify_workflow.py --all --json
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any, Dict, Tuple

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT / "assests"))

from assests.assest import (
    UserAssetManager,
    validate_schema_with_conditions,
    validate_user_assets_batch,
    ASSET_CATEGORY_PROFILE,
    ASSET_CATEGORY_PREFERENCES,
)


def run_single(manager: UserAssetManager, category: str, field: str, value: Any) -> Tuple[bool, str]:
    valid, err = validate_schema_with_conditions(manager, category, field, value)
    return valid, err or ""


def run_batch(manager: UserAssetManager, assets: Dict[str, Dict[str, Any]]) -> Tuple[bool, Dict[str, Any]]:
    valid, errors = validate_user_assets_batch(manager, assets)
    return valid, errors


def run_quick_checks(manager: UserAssetManager) -> Dict[str, Any]:
    results: Dict[str, Any] = {}

    ok, err = run_single(manager, ASSET_CATEGORY_PROFILE, "email", "test@example.com")
    results["single_valid_email"] = {"ok": ok, "error": err}

    assets = {
        ASSET_CATEGORY_PROFILE: {"email": "test@example.com", "first_name": "Test"},
        ASSET_CATEGORY_PREFERENCES: {"theme": "dark"},
    }
    ok_batch, errors = run_batch(manager, assets)
    results["batch_valid"] = {"ok": ok_batch, "errors": errors}

    ok_inv, err_inv = run_single(manager, ASSET_CATEGORY_PROFILE, "email", "invalid-email")
    results["invalid_email_rejected"] = {"ok": not ok_inv, "error": err_inv}

    results["summary_ok"] = all(v["ok"] for k, v in results.items() if k != "summary_ok")
    return results


def print_human(results: Dict[str, Any]) -> None:
    print("\n" + "=" * 60)
    print("HIZLI TEST DOGRULAMASI")
    print("=" * 60)

    for k, v in results.items():
        if k == "summary_ok":
            continue
        status = "OK" if v["ok"] else "FAIL"
        print(f"- {k}: {status}")
        if not v["ok"]:
            print(f"    error: {v.get('error') or v.get('errors')}")

    print("\n" + "=" * 60)
    print("COMPLETE - All quick checks passed" if results.get("summary_ok") else "COMPLETE - Some checks failed")
    print("=" * 60 + "\n")


def build_arg_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description="Hızlı test doğrulama aracı")
    group = p.add_mutually_exclusive_group()
    group.add_argument("--all", action="store_true", help="Run all quick checks")
    group.add_argument("--single", nargs=3, metavar=("CATEGORY", "FIELD", "VALUE"), help="Run a single validation")
    p.add_argument("--batch-file", type=str, help="Path to a JSON file containing assets to validate")
    p.add_argument("--json", action="store_true", help="Emit JSON output")
    p.add_argument("--quiet", action="store_true", help="Minimal output")
    p.add_argument("--output", type=str, help="Write JSON result to the given file path")
    p.add_argument("--report-format", choices=("junit",), help="Emit report in specified format (e.g. junit)")
    p.add_argument("--junit", type=str, help="(shorthand) Write JUnit XML to the given file path")
    return p


def write_junit_report(results: Dict[str, Any], path: str) -> None:
    """Write a minimal JUnit XML report for the provided results mapping.

    results is expected to be a mapping where each key (except 'summary_ok') is
    a check with a dict containing at least an 'ok' boolean and optional 'error' or 'errors'.
    """
    import xml.etree.ElementTree as ET
    from datetime import datetime

    tests = 0
    failures = 0

    testsuite = ET.Element("testsuite")
    testsuite.set("name", "verify_workflow_quick_checks")
    testsuite.set("timestamp", datetime.utcnow().isoformat() + "Z")

    for name, info in results.items():
        if name == "summary_ok":
            continue
        tests += 1
        tc = ET.SubElement(testsuite, "testcase")
        tc.set("name", name)
        tc.set("classname", "verify_workflow")
        if not info.get("ok"):
            failures += 1
            msg = info.get("error") or json.dumps(info.get("errors", {}))
            fail = ET.SubElement(tc, "failure")
            fail.set("message", str(msg))
            fail.text = str(msg)

    testsuite.set("tests", str(tests))
    testsuite.set("failures", str(failures))

    tree = ET.ElementTree(testsuite)
    with open(path, "wb") as fh:
        tree.write(fh, encoding="utf-8", xml_declaration=True)


def main(argv: list[str] | None = None) -> int:
    argv = list(argv) if argv is not None else sys.argv[1:]
    parser = build_arg_parser()
    args = parser.parse_args(argv)

    manager = UserAssetManager()

    if args.single:
        category, field, value = args.single
        ok, err = run_single(manager, category, field, value)
        out = {"ok": ok, "error": err}
        if args.json:
            print(json.dumps(out))
        elif not args.quiet:
            print(f"Single check: {'OK' if ok else 'FAIL'}")
            if err:
                print("  error:", err)
        # JUnit output for single
        if args.junit or args.report_format == "junit":
            junit_path = args.junit if args.junit else args.output
            if not junit_path:
                print("No output path provided for JUnit report (use --junit or --output)")
                return 5
            try:
                write_junit_report({"single": {"ok": ok, "error": err}, "summary_ok": ok}, junit_path)
            except Exception as e:
                print(f"Failed to write JUnit report: {e}")
                return 6

        return 0 if ok else 2

    if args.batch_file:
        # Load a JSON file with the expected assets mapping
        try:
            with open(args.batch_file, "r", encoding="utf-8") as fh:
                payload = json.load(fh)
        except Exception as e:
            print(f"Failed to read batch file: {e}")
            return 3

        # Expecting payload to be a mapping of category -> { field: value }
        ok, errors = run_batch(manager, payload)
        out = {"batch_valid": {"ok": ok, "errors": errors}}
        if args.output:
            try:
                with open(args.output, "w", encoding="utf-8") as fh:
                    json.dump(out, fh, ensure_ascii=False, indent=2)
            except Exception as e:
                print(f"Failed to write output file: {e}")
                return 4

        if args.json:
            print(json.dumps(out, ensure_ascii=False))
        elif not args.quiet:
            print_human({"batch_valid": out["batch_valid"], "summary_ok": out["batch_valid"]["ok"]})

        # If user requested JUnit or report-format, write JUnit
        if args.junit or args.report_format == "junit":
            junit_path = args.junit if args.junit else args.output
            if not junit_path:
                print("No output path provided for JUnit report (use --junit or --output)")
                return 5
            try:
                write_junit_report({"batch_valid": out["batch_valid"], "summary_ok": out["batch_valid"]["ok"]}, junit_path)
            except Exception as e:
                print(f"Failed to write JUnit report: {e}")
                return 6

        return 0 if ok else 2

    if args.all:
        results = run_quick_checks(manager)
        if args.json:
            print(json.dumps(results))
        elif not args.quiet:
            print_human(results)
        # If JUnit requested, write the JUnit report to --output or --junit
        if args.junit or args.report_format == "junit":
            junit_path = args.junit if args.junit else args.output
            if not junit_path:
                print("No output path provided for JUnit report (use --junit or --output)")
                return 5
            try:
                write_junit_report({**results}, junit_path)
            except Exception as e:
                print(f"Failed to write JUnit report: {e}")
                return 6

        return 0 if results.get("summary_ok") else 2

    # Default behavior: run all and print human
    results = run_quick_checks(manager)
    print_human(results)
    # If JUnit requested for default path
    if args.junit or args.report_format == "junit":
        junit_path = args.junit if args.junit else args.output
        if not junit_path:
            print("No output path provided for JUnit report (use --junit or --output)")
            return 5
        try:
            write_junit_report({**results}, junit_path)
        except Exception as e:
            print(f"Failed to write JUnit report: {e}")
            return 6

    return 0 if results.get("summary_ok") else 2


if __name__ == "__main__":
    raise SystemExit(main())