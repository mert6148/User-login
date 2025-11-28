// assets_test.go
// Package: assets_test
// Purpose: Tests for the 'sys' program assets directory. The tests verify
// that the assets directory exists, contains files, and that each asset file
// is readable and non-empty.

package assets_test

import (
	. "gopkg.in/check.v1"
	"io/ioutil"
	"os"
	"path/filepath"
	"testing"
)

// Register gocheck with `go test`.
func Test(t *testing.T) { TestingT(t) }

type S struct{}

var _ = Suite(&S{})

// assetsDir returns the directory to test. It checks the SYS_ASSETS_DIR
// environment variable and falls back to ./assets if unset.
func assetsDir() string {
	if d := os.Getenv("SYS_ASSETS_DIR"); d != "" {
		return d
	}
	return "./assets"
}

// TestAssetsDirectoryExists checks that the assets directory exists and is a directory.
func (s *S) TestAssetsDirectoryExists(c *C) {
	d := assetsDir()
	info, err := os.Stat(d)
	c.Assert(err, IsNil, Commentf("could not stat assets dir %q: %v", d, err))
	c.Assert(info.IsDir(), Equals, true, Commentf("%q is not a directory", d))
}

// TestAssetsContainFiles checks that at least one file exists in the assets directory.
func (s *S) TestAssetsContainFiles(c *C) {
	d := assetsDir()
	entries, err := ioutil.ReadDir(d)
	c.Assert(err, IsNil, Commentf("failed to read assets dir %q: %v", d, err))

	// filter out subdirectories
	count := 0
	for _, e := range entries {
		if !e.IsDir() {
			count++
		}
	}
	c.Assert(count > 0, Equals, true, Commentf("no files found in assets dir %q", d))
}

// TestAssetFilesNonEmpty iterates all regular files in the assets directory and
// asserts that none are empty and that each is readable.
func (s *S) TestAssetFilesNonEmpty(c *C) {
	d := assetsDir()
	walkErr := filepath.Walk(d, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if info.Mode().IsRegular() {
			// try to read file
			b, err := ioutil.ReadFile(path)
			c.Assert(err, IsNil, Commentf("could not read asset %q: %v", path, err))
			c.Assert(len(b) > 0, Equals, true, Commentf("asset %q is empty", path))
		}
		return nil
	})
	c.Assert(walkErr, IsNil)
}

// Optional helper: list expected assets via environment variable. If
// SYS_EXPECTED_ASSETS is set (comma-separated), the test will ensure those
// specific files exist relative to the assets dir.
func (s *S) TestExpectedAssetsExist(c *C) {
	list := os.Getenv("SYS_EXPECTED_ASSETS")
	if list == "" {
		c.Skip("SYS_EXPECTED_ASSETS not set; skipping specific-file checks")
		return
	}
	d := assetsDir()
	for _, name := range filepath.SplitList(list) {
		p := filepath.Join(d, name)
		info, err := os.Stat(p)
		c.Assert(err, IsNil, Commentf("expected asset %q missing: %v", p, err))
		c.Assert(info.Mode().IsRegular(), Equals, true, Commentf("expected asset %q is not a regular file", p))
	}
}
