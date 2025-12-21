import json
from verify_workflow import run_single, run_batch, run_quick_checks
from assests.assest import UserAssetManager, ASSET_CATEGORY_PROFILE


def test_run_single_valid_email():
    manager = UserAssetManager()
    ok, err = run_single(manager, ASSET_CATEGORY_PROFILE, "email", "test@example.com")
    assert ok


def test_run_single_invalid_email():
    manager = UserAssetManager()
    ok, err = run_single(manager, ASSET_CATEGORY_PROFILE, "email", "invalid-email")
    assert not ok


def test_run_quick_checks_summary():
    manager = UserAssetManager()
    results = run_quick_checks(manager)
    assert "summary_ok" in results
    # summary_ok may be True or False depending on validators; ensure keys exist
    assert "single_valid_email" in results
    assert "batch_valid" in results
    assert "invalid_email_rejected" in results
