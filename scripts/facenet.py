import sys
import json
import os
import numpy as np

# Suppress TensorFlow logs (must be set before importing TF/DeepFace)
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

import logging
logging.getLogger('tensorflow').setLevel(logging.ERROR)

try:
    import tensorflow as tf
    tf.get_logger().setLevel('ERROR')
except Exception:
    pass

from deepface import DeepFace
from scipy.spatial.distance import cosine

# --- CONFIG ---
DATABASE_FILE = sys.argv[2] 
MODEL_NAME = "Facenet512" 

# --- TUNING KNOBS ---
# 0.4 = Strict (Passports, Security)
# 0.5 = Forgiving (Parties, Makeup, Angles) <--- WE USE THIS
# 0.6 = Loose (Might match siblings/lookalikes)
THRESHOLD = 0.5

# --- HELPER ---
def get_all_embeddings(img_path):
    # RetinaFace is the "Eagle Eye" - it finds faces even if turned or covered by hair
    backends = ["retinaface", "mtcnn", "ssd", "opencv"]

    for backend in backends:
        try:
            embedding_objs = DeepFace.represent(
                img_path=img_path,
                model_name=MODEL_NAME,
                detector_backend=backend,
                enforce_detection=True,
                align=True # <--- CRITICAL: Rotates face to be upright
            )
            
            # Normalize output structure
            results = []
            if isinstance(embedding_objs, dict):
                embedding_objs = [embedding_objs]
                
            for obj in embedding_objs:
                if isinstance(obj, dict) and "embedding" in obj:
                    results.append(obj["embedding"])
            
            if results: 
                return results

        except Exception:
            continue

    # Final attempt: Skip detection (Use full image if cropped close)
    try:
        embedding_objs = DeepFace.represent(
            img_path=img_path,
            model_name=MODEL_NAME,
            detector_backend="skip",
            enforce_detection=False,
            align=True
        )
        if isinstance(embedding_objs, list):
            return [obj.get("embedding") for obj in embedding_objs if "embedding" in obj]
        elif isinstance(embedding_objs, dict):
            return [embedding_objs.get("embedding")]
    except Exception:
        pass
        
    return []

# --- MAIN LOGIC ---
if len(sys.argv) < 2:
    print(json.dumps({"error": "Missing arguments"}))
    sys.exit(1)

command = sys.argv[1] # 'index' or 'search'

# === INDEX: Save EVERY face found in the photo ===
if command == 'index':
    if len(sys.argv) < 4:
        print(json.dumps({"error": "Missing image path"}))
        sys.exit(1)

    img_path = sys.argv[3]
    
    embeddings = get_all_embeddings(img_path)

    if embeddings:
        if os.path.exists(DATABASE_FILE):
            try:
                with open(DATABASE_FILE, 'r') as f:
                    content = f.read().strip()
                    db = json.loads(content) if content else []
                    if not isinstance(db, list): db = []
            except:
                db = []
        else:
            db = []

        count = 0
        for emb in embeddings:
            db.append({
                "filename": os.path.basename(img_path),
                "embedding": emb
            })
            count += 1

        with open(DATABASE_FILE, 'w') as f:
            json.dump(db, f)

        print(json.dumps({"status": "success", "message": f"Indexed {count} faces from image"}))
    else:
        print(json.dumps({"status": "skipped", "message": "No faces found"}))

# === SEARCH: Find matches ===
elif command == 'search':
    if len(sys.argv) < 4:
        print(json.dumps({"error": "Missing selfie path"}))
        sys.exit(1)

    selfie_path = sys.argv[3]
    
    selfie_embeddings = get_all_embeddings(selfie_path)
    
    if not selfie_embeddings:
        print(json.dumps({"error": "No face detected in selfie."}))
        sys.exit(0)

    # Use the largest face found in selfie
    selfie_vector = selfie_embeddings[0]

    if not os.path.exists(DATABASE_FILE):
        print(json.dumps({"matches": []}))
        sys.exit(0)

    try:
        with open(DATABASE_FILE, 'r') as f:
            db = json.load(f)
    except:
        db = []

    matches = set()
    
    # Optional: Collect scores to debug closeness
    # debug_matches = [] 

    for record in db:
        db_vector = record['embedding']
        distance = cosine(selfie_vector, db_vector)
        
        # Using the looser Threshold (0.5)
        if distance < THRESHOLD: 
            matches.add(record['filename'])
            # debug_matches.append({'file': record['filename'], 'score': distance})

    print(json.dumps({"matches": list(matches)}))

else:
    print(json.dumps({"error": "Invalid command"}))

