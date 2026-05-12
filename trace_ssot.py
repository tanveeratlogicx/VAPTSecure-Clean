import sys
import json
import networkx as nx
from networkx.readwrite import json_graph
from pathlib import Path

def trace_ssot():
    graph_path = Path('graphify-out/graph.json')
    if not graph_path.exists():
        print("Error: graph.json not found.")
        return

    data = json.loads(graph_path.read_text(encoding='utf-8'))
    G = json_graph.node_link_graph(data, edges='links')

    # Target the SSoT community (identified as Community 28 in the report)
    # We'll look for the key maintenance scripts
    scripts = [
        'align_batch_system',
        'repair_drifts_script',
        'verify_integrity_script',
        'ssot_data_system'
    ]
    
    found_nodes = []
    for s in scripts:
        if s in G:
            found_nodes.append(s)
        else:
            # Try fuzzy match
            matches = [n for n in G.nodes if s in n.lower()]
            found_nodes.extend(matches)
    
    found_nodes = list(set(found_nodes)) # dedup
    
    print(f"Tracing SSoT Maintenance Flow ({len(found_nodes)} core nodes found)")
    
    for nid in found_nodes:
        label = G.nodes[nid].get('label', nid)
        comm = G.nodes[nid].get('community', 'N/A')
        print(f"\nNODE: {label} (Comm: {comm})")
        
        # Immediate neighbors
        for neighbor in G.neighbors(nid):
            edge_data = G.edges[nid, neighbor]
            node_data = G.nodes[neighbor]
            relation = edge_data.get('relation', 'connected_to')
            n_label = node_data.get('label', neighbor)
            n_comm = node_data.get('community', 'N/A')
            print(f"  --{relation}--> {n_label} (Comm: {n_comm})")

trace_ssot()
