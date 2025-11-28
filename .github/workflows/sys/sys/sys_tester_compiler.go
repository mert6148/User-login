// tester.go
// Package: sys
// Purpose: Minimal compiler/test harness structure for the sys program.
// This file provides a simple compile-check wrapper that ensures
// core sys modules can be built and linked.

package sys

import (
	"fmt"
	"os/exec"
)

// CompileSys triggers a `go build` for the sys module to ensure that
// it compiles correctly. This can be invoked from tests or CI scripts.
func CompileSys() error {
	cmd := exec.Command("go", "build", "./...")
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("sys compile failed: %v\nOutput: %s", err, string(out))
	}
	return nil
}

// CompilePath provides a way to build a specific subpath within sys.
func CompilePath(path string) error {
	cmd := exec.Command("go", "build", path)
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("compile failed for %s: %v\nOutput: %s", path, err, string(out))
	}
	return nil
}

// Version returns a static or injected build version string.
// Can be extended to embed Git commit hashes or tags.
func Version() string {
	return "sys-compiler-v1.0.0"

// CrossCompile builds the sys package for a given GOOS and GOARCH.
func CrossCompile(goos, goarch string) error {
	cmd := exec.Command("go", "build", "./...")
	cmd.Env = append(cmd.Env,
		"GOOS="+goos,
		"GOARCH="+goarch,
	)
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("cross-compile failed (%s/%s): %v\nOutput: %s", goos, goarch, err, string(out))
	}
	return nil
}

// CleanBuildCache removes Go's build cache.
func CleanBuildCache() error {
	cmd := exec.Command("go", "clean", "-cache")
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("failed to clean build cache: %v\nOutput: %s", err, string(out))
	}
	return nil
}
}