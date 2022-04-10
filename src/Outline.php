<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Dompdf;

use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * Represents the bookmarks structure.
 *
 * @package dompdf
 */
class Outline
{
    /**
     * @var Options
     */
    private $options;

    /**
     * Maps outline tree nodes to their parents.
     *
     * @var array<string, ?DOMNode>
     */
    private $parents = [];

    public function __construct(Options $options) {
        $this->options = $options;
    }

    /**
     * Builds an outline tree according to the configuration.
     */
    public function from_document(DOMXPath $xpath): void {
        $outline_selector = $this->options->getOutlineSelector();
        if ($outline_selector) {
            $outline_headings = $xpath->query($outline_selector);
            if ($outline_headings) {
                $this->add_headings($outline_headings);
            }
        }
    }

    /**
     * Adds the headings using their levels to build the structure.
     */
    protected function add_headings(DOMNodeList $headings): void {
        $last = [];
        foreach ($headings as $heading) {
            $parent = null;
            $level = (int)substr($heading->tagName, 1);
            foreach ($last as $lastLevel => $lastHeading) {
                if ($lastLevel >= $level) {
                    unset($last[$lastLevel]);
                } else {
                    $parent = $lastHeading;
                }
            }
            $last[$level] = $heading;
            $this->add($heading, $parent);
        }
    }

    /**
     * Lets the frame know it's outline id and parent.
     */
    public function decorate_frame(Frame $frame): void {
        $node = $frame->get_node();
        if ($this->contains($node)) {
            $outline_id = $this->get_node_id($node);
            $frame->set_outline_id($outline_id);
            $outline_parent = $this->get_parent($node);
            if ($outline_parent) {
                $outline_parent_id = $this->get_node_id($outline_parent);
                $frame->set_outline_parent_id($outline_parent_id);
            }
        }
    }

    /**
     * Adds the node to the outline tree under the parent.
     */
    public function add(DOMNode $node, ?DOMNode $parent): void {
        $node_id = $this->get_node_id($node);
        $this->parents[$node_id] = $parent;
    }

    /**
     * Checks if the node is to be included in the outline.
     */
    public function contains(DOMNode $node): bool {
        $node_id = $this->get_node_id($node);
        return array_key_exists($node_id, $this->parents);
    }

    /**
     * Returns the parent of the node in the outline tree.
     */
    public function get_parent(DOMNode $node): ?DOMNode {
        $node_id = $this->get_node_id($node);
        return $this->parents[$node_id];
    }

    /**
     * Returns the total count of the items in the outline.
     */
    public function get_count(): int {
        return count($this->parents);
    }

    /**
     * Returns a unique id for the node.
     */
    protected function get_node_id(DOMNode $node): string {
        return $node->getNodePath();
    }
}
