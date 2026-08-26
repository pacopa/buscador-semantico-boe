from __future__ import annotations

import hashlib
import math
import os
from functools import lru_cache
from typing import List

from fastapi import FastAPI  # type: ignore[import-not-found]
from pydantic import BaseModel, Field  # type: ignore[import-not-found]

MODEL_NAME = os.getenv("MODEL_NAME", "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2")
ALLOW_HASH_FALLBACK = os.getenv("ALLOW_HASH_FALLBACK", "true").lower() in {"1", "true", "yes"}

app = FastAPI(title="Local Embedding Service", version="0.1.0")


class EmbedRequest(BaseModel):
    texts: List[str] = Field(min_length=1, max_length=64)


class EmbedResponse(BaseModel):
    model: str
    dimensions: int
    embeddings: List[List[float]]
    fallback: bool = False


@lru_cache(maxsize=1)
def load_model():
    from sentence_transformers import SentenceTransformer  # type: ignore[import-not-found]

    return SentenceTransformer(MODEL_NAME)


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "fallbackAllowed": ALLOW_HASH_FALLBACK}


@app.post("/embed", response_model=EmbedResponse)
def embed(request: EmbedRequest):
    try:
        model = load_model()
        vectors = model.encode(request.texts, normalize_embeddings=True).tolist()
        dimensions = len(vectors[0]) if vectors else 0
        return EmbedResponse(model=MODEL_NAME, dimensions=dimensions, embeddings=vectors, fallback=False)
    except Exception:
        if not ALLOW_HASH_FALLBACK:
            raise

        vectors = [_hash_embedding(text) for text in request.texts]
        return EmbedResponse(model="hash-fallback", dimensions=len(vectors[0]), embeddings=vectors, fallback=True)


def _hash_embedding(text: str, dimensions: int = 384) -> List[float]:
    vector = [0.0] * dimensions
    for token in _tokens(text):
        digest = hashlib.sha256(token.encode("utf-8")).digest()
        index = int.from_bytes(digest[:4], "big") % dimensions
        sign = 1.0 if digest[4] % 2 else -1.0
        vector[index] += sign

    norm = math.sqrt(sum(value * value for value in vector))
    if norm == 0:
        return vector

    return [value / norm for value in vector]


def _tokens(text: str) -> List[str]:
    normalized = "".join(char.lower() if char.isalnum() else " " for char in text)
    return [part for part in normalized.split() if part]
