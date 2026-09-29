"""Verify the distributable, rather than only the working directory."""
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import zipfile

archive = subprocess.check_output(["git", "archive", "--worktree-attributes", "--format=zip", "HEAD"])
with zipfile.ZipFile(io.BytesIO(archive)) as package:
    names = package.namelist()
    allowed = ("src/", "Resources/", "config/", "templates/", "translations/")
    leaked = [name for name in names if name not in ("composer.json", "README.md", "LICENSE") and not name.startswith(allowed)]
    assert not leaked, f"Development files in distribution: {leaked}"
    for required in ["composer.json", "LICENSE", "README.md", "config/services.yaml", "src/ContaoManager/Plugin.php", "Resources/contao/templates/content_element/withdrawal.html.twig"]:
        assert required in names, f"Missing runtime file: {required}"

manifest = json.loads(Path("composer.json").read_text())
assert manifest["type"] == "contao-bundle"
assert manifest["license"] == "MIT"
assert {"widerrufsbutton", "356a-bgb"}.issubset(manifest.get("keywords", []))
assert "356a" in manifest["description"]
assert "make check" in Path(".github/workflows/check.yaml").read_text()
print(f"Package export verified: {len(names)} entries, no development files.")

with tempfile.TemporaryDirectory(prefix="withdrawal-package-") as directory:
    artifact = Path(directory) / "audit.zip"
    subprocess.run([sys.executable, "scripts/build-artifact.py", "0.0.0-dev", str(artifact)], check=True, stdout=subprocess.DEVNULL)
    with zipfile.ZipFile(artifact) as package:
        assert json.loads(package.read("composer.json"))["version"] == "0.0.0-dev"
        assert package.namelist().count("composer.json") == 1
        assert set(package.namelist()) == set(names)
print("Contao Manager artifact verified: root manifest with a unique version entry.")

with tempfile.TemporaryDirectory(prefix="withdrawal-invalid-version-") as directory:
    artifact = Path(directory) / "invalid.zip"
    result = subprocess.run([sys.executable, "scripts/build-artifact.py", "1.0.0-garbage", str(artifact)], capture_output=True)
    assert result.returncode != 0 and not artifact.exists(), "Builder must reject Composer-incompatible version suffixes"
