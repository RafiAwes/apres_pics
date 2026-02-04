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

# --- HELPER ---
def get_all_embeddings(img_path):
    backends = ["retinaface", "ssd", "mtcnn", "opencv"]

    for backend in backends:
        try:
            embedding_objs = DeepFace.represent(
                img_path=img_path,
                model_name=MODEL_NAME,
                detector_backend=backend,
                enforce_detection=True
            )
            if isinstance(embedding_objs, dict):
                return [embedding_objs.get("embedding")]
            return [obj.get("embedding") for obj in embedding_objs if isinstance(obj, dict)]
        except Exception:
            continue

    # Final attempt without enforcing detection (avoids false negatives)
    try:
        embedding_objs = DeepFace.represent(
            img_path=img_path,
            model_name=MODEL_NAME,
            detector_backend="opencv",
            enforce_detection=False
        )
        if isinstance(embedding_objs, dict):
            return [embedding_objs.get("embedding")]
        return [obj.get("embedding") for obj in embedding_objs if isinstance(obj, dict)]
    except Exception:
        pass

    # Last resort: skip detection entirely (uses full image)
    try:
        embedding_objs = DeepFace.represent(
            img_path=img_path,
            model_name=MODEL_NAME,
            detector_backend="skip",
            enforce_detection=False
        )
        if isinstance(embedding_objs, dict):
            return [embedding_objs.get("embedding")]
        return [obj.get("embedding") for obj in embedding_objs if isinstance(obj, dict)]
    except Exception:
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
    
    # 1. Get ALL embeddings (Group photo support)
    embeddings = get_all_embeddings(img_path)

    if embeddings:
        # 2. Load DB
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

        # 3. Append EVERY face as a separate record
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
    
    # For selfie, we usually just take the first/largest face
    selfie_embeddings = get_all_embeddings(selfie_path)
    
    if not selfie_embeddings:
        print(json.dumps({"error": "No face detected in selfie."}))
        sys.exit(0)

    # Assume the user is the main face in the selfie
    selfie_vector = selfie_embeddings[0]

    if not os.path.exists(DATABASE_FILE):
        print(json.dumps({"matches": []}))
        sys.exit(0)

    try:
        with open(DATABASE_FILE, 'r') as f:
            db = json.load(f)
    except:
        db = []

    matches = set() # Use a Set to avoid duplicates (same photo matching twice)

    # Compare
    for record in db:
        db_vector = record['embedding']
        distance = cosine(selfie_vector, db_vector)
        
        # 0.4 is the industry standard for Facenet512
        if distance < 0.4: 
            matches.add(record['filename'])

    # Return List
    print(json.dumps({"matches": list(matches)}))

else:
    print(json.dumps({"error": "Invalid command"}))