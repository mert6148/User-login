import json
import tempfile
from pathlib import Path
from verify_workflow import main


def test_batch_file_writes_output(tmp_path):
    # create a batch file
    batch = tmp_path / "batch.json"
    batch.write_text(json.dumps({
        "profile": {"email": "test@example.com"}
    }))

    out_file = tmp_path / "out.json"

    # run main with batch-file and output
    rc = main(["--batch-file", str(batch), "--json", "--output", str(out_file)])
    assert rc == 0

    assert out_file.exists()
    data = json.loads(out_file.read_text())
    assert "batch_valid" in data
    assert "ok" in data["batch_valid"]
