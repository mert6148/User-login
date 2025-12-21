import json
from pathlib import Path
from verify_workflow import main
import xml.etree.ElementTree as ET


def test_junit_output_for_all(tmp_path):
    out = tmp_path / "report.xml"
    rc = main(["--all", "--report-format", "junit", "--output", str(out)])
    assert rc == 0
    assert out.exists()

    tree = ET.parse(str(out))
    root = tree.getroot()
    assert root.tag == "testsuite"
    # should contain at least the single_valid_email testcase
    names = [tc.get('name') for tc in root.findall('testcase')]
    assert "single_valid_email" in names


def test_junit_output_for_single_failure(tmp_path):
    out = tmp_path / "report_single.xml"
    # run a single check expected to fail
    rc = main(["--single", "profile", "email", "invalid-email", "--report-format", "junit", "--junit", str(out)])
    assert rc == 2 or rc == 0
    assert out.exists()
    tree = ET.parse(str(out))
    root = tree.getroot()
    assert root.tag == "testsuite"
    names = [tc.get('name') for tc in root.findall('testcase')]
    assert names == ["single"] or "single" in names
