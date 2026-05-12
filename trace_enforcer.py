import sys
import json
import networkx as nx
from networkx.readwrite import json_graph
from pathlib import Path

def trace():
    graph_path = Path('graphify-out/graph.json')
    if not graph_path.exists():
        print("Error: graph.json not found.")
        return

    data = json.loads(graph_path.read_text(encoding='utf-8'))
    G = json_graph.node_link_graph(data, edges='links')

    target_id = 'includes_class_vaptsecure_enforcer_vaptsecure_enforcer'
    if target_id not in G:
        # Try to find a node that looks like the Enforcer
        candidates = [n for n in G.nodes if 'vaptsecure_enforcer' in n.lower()]
        if not candidates:
            print("Could not find Enforcer node.")
            return
        target_id = candidates[0]

    print(f"Tracing connections for: {G.nodes[target_id].get('label', target_id)}")
    
    # BFS depth 1 to see immediate neighbors and their communities
    neighbors = list(G.neighbors(target_id))
    
    print(f"\nImmediate Connections ({len(neighbors)}):")
    for n in neighbors:
        edge_data = G.edges[target_id, n]
        node_data = G.nodes[n]
        relation = edge_data.get('relation', 'connected_to')
        confidence = edge_data.get('confidence', 'UNKNOWN')
        community = node_data.get('community', 'N/A')
        label = node_data.get('label', n)
        print(f"  --{relation}--> {label} (Comm: {community}, Conf: {confidence})")

    # Hyperedges involving this node
    hyper_path = Path('.graphify_extract.json') # This was deleted, but let's check graph.json links
    # Actually graphify.export.to_json doesn't export hyperedges in a way that's easy to read back
    # But we can look for "participate_in" edges if they exist.

trace()
